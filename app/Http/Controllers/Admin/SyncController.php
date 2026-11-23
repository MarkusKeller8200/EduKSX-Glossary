<?php

namespace App\Http\Controllers\Admin;

use App\Access\Audit;
use App\Http\Controllers\Controller;
use App\Models\SyncPeer;
use App\Support\DatabaseDump;
use App\Support\Settings;
use App\Sync\DevSync;
use App\Sync\PeerClient;
use App\Sync\SyncRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

/**
 * Administration › System › Sync (#478, #503, DEC-041 A20): Gegenstellen erfassen, vergleichen, pro Datensatz
 * synchronisieren und die ganze Instanz ersetzen («Holen»).
 * Nur mit Recht «system» (Developer).
 */
class SyncController extends Controller
{
    public function __construct(private readonly DevSync $sync) {}

    public function index(): Response
    {
        return $this->page();
    }

    /** Vergleichstabelle mit einer Gegenstelle (#503). */
    public function compare(SyncPeer $peer): Response
    {
        try {
            $vergleich = $this->sync->compare($peer);
        } catch (Throwable $e) {
            $vergleich = ['fehler' => PeerClient::describe($e)];
        }

        return $this->page($peer, $vergleich);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'url' => ['required', 'string', 'max:255'],
            'token' => ['required', 'string', 'max:200', 'regex:/^(glossary_sa_|gls_)[A-Za-z0-9]+$/'],
        ], ['token.regex' => 'Token eines Servicekontos der Gegenstelle (beginnt mit «glossary_sa_»).']);
        $url = rtrim(trim($data['url']), '/');
        if (! PeerClient::validUrl($url)) {
            throw ValidationException::withMessages(['url' => 'Adresse mit https://, ohne Pfad-Zusätze wie ?…']);
        }
        $peer = new SyncPeer(['name' => trim($data['name']), 'url' => $url, 'token' => $data['token']]);
        $peer->created_by_id = $user->id;
        $peer->save();
        Audit::log($user, null, 'sync.peer.created', ['peer' => $peer->name, 'url' => $url]);

        return redirect("/admin/system/sync/gegenstellen/{$peer->id}")->with('status', "Gegenstelle «{$peer->name}» erfasst.");
    }

    public function destroy(Request $request, SyncPeer $peer): RedirectResponse
    {
        $user = $this->currentUser($request);
        $peer->delete();
        Audit::log($user, null, 'sync.peer.deleted', ['peer' => $peer->name]);

        return redirect('/admin/system/sync')->with('status', "Gegenstelle «{$peer->name}» entfernt.");
    }

    public function pull(Request $request, SyncPeer $peer): RedirectResponse
    {
        $user = $this->currentUser($request);
        $data = $request->validate([
            'bestaetigung' => ['required', 'in:HOLEN'],
            'passwort' => ['nullable', 'string', 'max:200'],
        ], ['bestaetigung.in' => 'Zur Bestätigung «HOLEN» eingeben.', 'bestaetigung.required' => 'Zur Bestätigung «HOLEN» eingeben.']);
        $password = (string) ($data['passwort'] ?? '');
        if ($password === '' && DatabaseDump::passwordRequired()) {
            throw ValidationException::withMessages(['passwort' => 'Für die Sicherung vorher ist ein Passwort vorgeschrieben.']);
        }
        try {
            $result = $this->sync->pull($peer, $user, $password === '' ? null : $password);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['holen' => PeerClient::describe($e)]);
        }

        return redirect("/admin/system/sync/gegenstellen/{$peer->id}")->with('status', "Von «{$peer->name}» geholt: {$result['rows']} Zeilen. Sicherung vorher: {$result['backup']}.");
    }

    /** Gewählte Datensätze in der gewählten Richtung synchronisieren (#503). */
    public function synchronize(Request $request, SyncPeer $peer): RedirectResponse
    {
        $user = $this->currentUser($request);
        $data = $request->validate([
            'eintraege' => ['required', 'array', 'max:5000'],
            'eintraege.*.typ' => ['required', 'in:term,categories,sources'],
            'eintraege.*.id' => ['required', 'string', 'max:64'],
            'eintraege.*.richtung' => ['required', 'in:push,pull'],
            'passwort' => ['nullable', 'string', 'max:200'],
        ], ['eintraege.required' => 'Keine Zeile zum Synchronisieren gewählt.']);
        $choices = array_values(array_map(fn (array $e): array => ['type' => (string) $e['typ'], 'id' => (string) $e['id'], 'direction' => (string) $e['richtung']], $data['eintraege']));
        $password = (string) ($data['passwort'] ?? '');
        $pulls = count(array_filter($choices, fn (array $c): bool => $c['direction'] === 'pull'));
        if ($pulls > 0 && $password === '' && DatabaseDump::passwordRequired()) {
            throw ValidationException::withMessages(['passwort' => 'Für die Sicherung vor dem Übernehmen ist ein Passwort vorgeschrieben.']);
        }
        try {
            $r = $this->sync->sync($peer, $choices, $user, $password === '' ? null : $password);
        } catch (RuntimeException $e) {
            return back()->withErrors(['sync' => PeerClient::describe($e)]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['sync' => PeerClient::describe($e)]);
        }
        $text = "Synchronisiert: {$r['pushed']} nach «{$peer->name}», {$r['pulled']} von «{$peer->name}» übernommen";
        if ($r['conflicts'] !== []) {
            $text .= '. Inzwischen geändert und darum nicht überschrieben: '.implode(', ', array_slice($r['conflicts'], 0, 5)).(count($r['conflicts']) > 5 ? ' …' : '').' – neu vergleichen';
        }
        if ($r['errors'] !== []) {
            $text .= '. Fehler: '.implode('; ', array_slice($r['errors'], 0, 5));
        }
        if ($r['messages'] !== []) {
            $text .= '. '.implode(' ', array_slice($r['messages'], 0, 5));
        }
        if ($r['backup'] !== null) {
            $text .= '. Sicherung vorher: '.$r['backup'];
        }

        return redirect("/admin/system/sync/gegenstellen/{$peer->id}")->with('status', $text.'.');
    }

    /** Beide Fassungen eines Datensatzes für die Detailansicht der Vergleichstabelle (#503). */
    public function detail(Request $request, SyncPeer $peer): JsonResponse
    {
        $data = $request->validate(['typ' => ['required', 'in:term,categories,sources'], 'id' => ['required', 'string', 'max:64']]);
        try {
            return response()->json($this->sync->detail($peer, (string) $data['typ'], (string) $data['id']));
        } catch (Throwable $e) {
            return response()->json(['fehler' => PeerClient::describe($e)], 502);
        }
    }

    /** @param  array<string, mixed>|null  $vergleich */
    private function page(?SyncPeer $peer = null, ?array $vergleich = null): Response
    {
        $role = SyncRole::current();

        return Inertia::render('admin/sync', [
            'rolle' => ['wert' => $role, 'label' => SyncRole::label($role), 'quelle' => Settings::source('sync.role')],
            'darfSync' => in_array($role, [SyncRole::DEVELOPMENT, SyncRole::TEST], true),
            'passwortNoetig' => DatabaseDump::passwordRequired(),
            'gegenstellen' => SyncPeer::query()->orderBy('name')->get()->map(fn (SyncPeer $p): array => [
                'id' => $p->id, 'name' => $p->name, 'url' => $p->url, 'rolle' => $p->remote_role !== null ? SyncRole::label($p->remote_role) : null,
                'geholt' => $p->last_pulled_at?->toIso8601String(), 'hochgeladen' => $p->last_pushed_at?->toIso8601String(),
                'ergebnis' => $p->last_result,
            ])->all(),
            'aktiv' => $peer?->id,
            'vergleich' => $vergleich,
        ]);
    }
}
