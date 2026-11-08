<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAudit;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            $items[] = $def + ['key' => $key, 'wert' => Settings::get($key), 'vorgabe' => Settings::default($key)];
        }
        $names = User::query()->pluck('name', 'id');

        return Inertia::render('admin/settings', [
            'einstellungen' => $items,
            'protokoll' => UserAudit::query()->where('action', 'settings.changed')->latest('id')->limit(50)->get()
                ->map(fn (UserAudit $e): array => [
                    'am' => $e->created_at->toIso8601String(),
                    'von' => $e->actor_id !== null ? (string) ($names[$e->actor_id] ?? '(gelöscht)') : (string) $e->actor_label,
                    'key' => (string) ($e->new['key'] ?? ''),
                    'alt' => $e->new['old'] ?? null,
                    'neu' => $e->new['new'] ?? null,
                ])->all(),
        ]);
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
