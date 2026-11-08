<?php

namespace App\Http\Controllers;

use App\Access\Audit;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Datenschutzerklärung (#42, DEC-019): Seite /datenschutz aus docs/DATENSCHUTZ-ENTWURF.md mit Version; Kenntnisnahme
 * je Version (keine pauschale Einwilligung). Bis zum Freigabecheck als Entwurf gekennzeichnet.
 */
class PrivacyController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('privacy', $this->content());
    }

    /** Kenntnisnahme verlangen (neue Version oder bisher keine). */
    public function acknowledge(): Response
    {
        return Inertia::render('privacy', $this->content() + ['kenntnisnahme' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $version = (string) config('glossary.privacy_version');
        $user->forceFill(['privacy_notice_version' => $version, 'privacy_notice_acknowledged_at' => now()])->save();
        Audit::log($user, $user, 'privacy.acknowledged', ['version' => $version]);

        return redirect()->intended('/konto')->with('status', 'Danke – Kenntnisnahme gespeichert.');
    }

    /** @return array{html: string, version: string, entwurf: bool} */
    private function content(): array
    {
        $markdown = (string) @file_get_contents(base_path('docs/DATENSCHUTZ-ENTWURF.md'));

        return [
            // eigene Datei; HTML darin wird entfernt, unsichere Links nicht zugelassen
            // Str::markdown nutzt GitHub-Flavored Markdown (Tabellen, Aufgabenlisten)
            'html' => (string) Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'version' => (string) config('glossary.privacy_version'),
            'entwurf' => Settings::bool('privacy.draft_notice'),
        ];
    }
}
