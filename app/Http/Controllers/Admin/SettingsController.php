<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAudit;
use App\Support\EnvironmentOverview;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Allgemeine Einstellungen (#106, Recht «settings»): anzeigen, speichern, Protokoll der Änderungen. */
class SettingsController extends Controller
{
    public function index(): Response
    {
        $items = [];
        foreach (Settings::DEFINITIONS as $key => $def) {
            $secret = $def['typ'] === 'secret';
            $items[] = $def + [
                'key' => $key,
                // Geheimnisse nie ausgeben, nur ob gesetzt
                'wert' => $secret ? '' : Settings::get($key),
                'vorgabe' => $secret ? '' : Settings::default($key),
                'gesetzt' => $secret ? Settings::string($key) !== '' : null,
                'gesperrt' => Settings::locked($key),
                'herkunft' => Settings::source($key),
            ];
        }
        $names = User::query()->pluck('name', 'id');

        return Inertia::render('admin/settings', [
            'einstellungen' => $items,
            // einmal angezeigt nach «Token erzeugen» (#366)
            'neuesGeheimnis' => session('neues_geheimnis'),
            // Umgebung nur für Developer (Recht «system»): Infrastruktur, Geheimnisse nur gesetzt / nicht gesetzt
            'umgebung' => Gate::allows('system') ? EnvironmentOverview::items() : null,
            'ordner' => Gate::allows('system') ? EnvironmentOverview::directory() : null,
            'protokoll' => UserAudit::query()->where('action', 'settings.changed')->latest('id')->limit(50)->get()
                ->map(fn (UserAudit $e): array => [
                    'am' => $e->created_at->toIso8601String(),
                    'von' => $e->actor_id !== null ? (string) ($names[$e->actor_id] ?? '(gelöscht)') : (string) $e->actor_label,
                    'key' => (string) ($e->new_values['key'] ?? ''),
                    'alt' => $e->new_values['old'] ?? null,
                    'neu' => $e->new_values['new'] ?? null,
                ])->all(),
        ]);
    }

    /** Geheimnis erzeugen bzw. erneuern oder entfernen (#366); der neue Wert wird genau einmal angezeigt. */
    public function secret(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate(['aktion' => ['required', 'in:erzeugen,entfernen']]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $value = $data['aktion'] === 'erzeugen' ? bin2hex(random_bytes(24)) : null;
        try {
            Settings::setSecret($user, $key, $value);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['werte' => $e->getMessage()]);
        }

        return $value === null
            ? back()->with('status', 'Entfernt.')
            : back()->with('status', 'Neu erzeugt – jetzt kopieren, es wird nicht mehr angezeigt.')->with('neues_geheimnis', ['key' => $key, 'wert' => $value]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['werte' => ['required', 'array']]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        try {
            $changed = Settings::set($user, (array) $data['werte']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['werte' => $e->getMessage()]);
        }

        return back()->with('status', $changed === [] ? 'Keine Änderung.' : count($changed).' Einstellung(en) gespeichert.');
    }
}
