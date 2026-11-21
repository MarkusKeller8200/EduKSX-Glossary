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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

/**
 * Administration › System › Sync (#478, DEC-041 A20): Gegenstellen erfassen, holen, vergleichen und hochladen.
 * Nur mit Recht «system» (Developer).
 */
class SyncController extends Controller
{
    public function __construct(private readonly DevSync $sync) {}

    public function index(): Response
    {
        return $this->page();
    }

    /** Vergleich mit einer Gegenstelle: was bereit zum Hochladen ist, was zuerst geholt werden muss. */
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

    public function upload(Request $request, SyncPeer $peer): RedirectResponse
    {
        $user = $this->currentUser($request);
        $data = $request->validate([
            'ids' => ['array', 'max:2000'],
            'ids.*' => ['uuid'],
            'veroeffentlichen' => ['boolean'],
        ]);
        try {
            $r = $this->sync->upload($peer, array_values($data['ids'] ?? []), (bool) ($data['veroeffentlichen'] ?? false), $user);
        } catch (RuntimeException $e) {
            return back()->withErrors(['hochladen' => PeerClient::describe($e)]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['hochladen' => PeerClient::describe($e)]);
        }
        $text = "Nach «{$peer->name}» hochgeladen: {$r['created']} neu, {$r['updated']} geändert";
        if ($r['conflicts'] !== []) {
            $text .= ', '.count($r['conflicts']).' als Konflikt abgelehnt (zuerst holen)';
        }
        if ($r['errors'] !== []) {
            $text .= '. Fehler: '.implode('; ', array_slice($r['errors'], 0, 5));
        }

        return back()->with('status', $text.($r['messages'] !== [] ? '. '.implode(' ', array_slice($r['messages'], 0, 5)) : '').'.');
    }

    /** @param  array<string, mixed>|null  $vergleich */
    private function page(?SyncPeer $peer = null, ?array $vergleich = null): Response
    {
        $role = SyncRole::current();

        return Inertia::render('admin/sync', [
            'rolle' => ['wert' => $role, 'label' => SyncRole::label($role), 'quelle' => Settings::source('sync.role')],
            'darfHolen' => in_array($role, [SyncRole::DEVELOPMENT, SyncRole::TEST], true),
            'darfHochladen' => in_array($role, [SyncRole::DEVELOPMENT, SyncRole::TEST], true),
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
