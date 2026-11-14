<?php

namespace App\Http\Controllers\Admin;

use App\Access\Audit;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DatabaseDump;
use App\Support\DevBackups;
use App\Support\DumpArchive;
use App\Support\DumpRestore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Vollständige Sicherung wiederherstellen in der Administration (#355) – nur auf dev (`GLOSSARY_DEV_BACKUPS=true`),
 * Recht «system». Konten mit Passwort und zweitem Faktor, Anmeldungen über Microsoft, Rollen und Sitzungen bleiben;
 * alles andere kommt aus der Sicherung. Vorher wird der aktuelle Stand automatisch gesichert.
 */
class DevBackupController extends Controller
{
    /** Sicherung auf dem Server anlegen */
    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'bezeichnung' => ['nullable', 'string', 'max:40'],
            'passwort' => [DatabaseDump::passwordRequired() ? 'required' : 'nullable', 'string', 'min:12', 'max:200', 'confirmed'],
        ]);
        try {
            $backup = DevBackups::create((string) ($data['passwort'] ?? ''), $data['bezeichnung'] ?? null);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['passwort' => $e->getMessage()]);
        }
        Audit::log($user, null, 'system.backup', ['datei' => $backup['name'], 'zeilen' => array_sum($backup['manifest']['tables'])]);

        return back()->with('status', 'Sicherung angelegt: '.$backup['name']);
    }

    /** Sicherung vom Server oder hochgeladene Datei einspielen */
    public function restore(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'sicherung' => ['nullable', 'required_without:datei', 'string', 'max:120'],
            'datei' => ['nullable', 'required_without:sicherung', 'file', 'max:10240'],
            'passwort' => ['nullable', 'string', 'max:200'],
            'bestaetigung' => ['required', 'in:WIEDERHERSTELLEN'],
        ], [
            'bestaetigung.in' => 'Zur Bestätigung «WIEDERHERSTELLEN» eingeben.',
            'bestaetigung.required' => 'Zur Bestätigung «WIEDERHERSTELLEN» eingeben.',
        ]);
        $password = (string) ($data['passwort'] ?? '');
        $upload = $request->file('datei');
        $field = $upload instanceof UploadedFile ? 'datei' : 'sicherung';
        try {
            $file = $upload instanceof UploadedFile ? (string) $upload->getRealPath() : DevBackups::path((string) $data['sicherung']);
            $dump = DumpArchive::open($file, $password);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([str_contains($e->getMessage(), 'Passwort') ? 'passwort' : $field => $e->getMessage()]);
        }
        $name = $upload instanceof UploadedFile ? $upload->getClientOriginalName() : (string) $data['sicherung'];
        $before = ['name' => ''];
        $result = ['tables' => [], 'dropped' => []];

        try {
            // aktueller Stand zuerst – Rückweg, falls die falsche Sicherung gewählt wurde
            $before = DevBackups::create(DatabaseDump::passwordRequired() ? $password : null, null, 'vor-wiederherstellung');
            $result = DumpRestore::runKeepingAccounts($dump, DB::connection());
            Artisan::call('cache:clear');
        } catch (Throwable $e) {
            Log::warning('glossary.restore.failed', ['file' => $name, 'exception' => $e::class, 'message' => $e->getMessage()]);
            Audit::log($user, null, 'system.restore', ['datei' => $name, 'fehler' => $e->getMessage()], 'failed');

            throw ValidationException::withMessages([$field => 'Wiederherstellung abgebrochen, Daten unverändert: '.$e->getMessage()]);
        } finally {
            $dump->close();
        }

        $rows = array_sum($result['tables']);
        $dropped = array_sum($result['dropped']);
        Audit::log($user, null, 'system.restore', [
            'datei' => $name,
            'erstellt' => (string) ($dump->manifest['created_at'] ?? ''),
            'tabellen' => count($result['tables']),
            'zeilen' => $rows,
            'entfallen' => $result['dropped'],
            'konten_behalten' => true,
            'sicherung_vorher' => $before['name'],
        ]);

        return back()->with('status', "Wiederhergestellt: {$rows} Zeilen aus {$name}. Konten, Rollen und Anmeldungen sind unverändert."
            .($dropped > 0 ? " {$dropped} Zeilen zu Konten, die es nicht mehr gibt, sind entfallen." : '')
            .' Vorheriger Stand: '.$before['name'].'.');
    }

    public function destroy(Request $request, string $name): RedirectResponse
    {
        $user = $this->user($request);
        try {
            DevBackups::delete($name);
        } catch (RuntimeException $e) {
            abort(404);
        }
        Audit::log($user, null, 'system.backup.deleted', ['datei' => basename($name)]);

        return back()->with('status', 'Sicherung gelöscht: '.basename($name));
    }

    private function user(Request $request): User
    {
        abort_unless(DevBackups::enabled(), 404);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
