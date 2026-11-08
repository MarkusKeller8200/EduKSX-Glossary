<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AppVersion;
use App\Support\Palette;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user?->only(['id', 'name', 'email']),
                // Rechte für die Anzeige (Knöpfe, Navigation); geprüft wird immer serverseitig (#36)
                'can' => $user !== null ? array_fill_keys($user->permissions(), true) : (object) [],
                'roles' => $user?->roleKeys() ?? [],
                // einmal nach der Anmeldung fragen, ob News erwünscht sind (#169)
                'newsFrage' => $user !== null && $user->isActive() && $user->news_answered_at === null,
            ],
            'appVersion' => AppVersion::info(),
            'links' => [
                // einstellbar (#106); leer = Link ausblenden
                'githubRepo' => Settings::string('links.github_repo') ?: null,
                'pages' => Settings::string('links.pages_url') ?: null,
            ],
            // Farben der Kategorien und Herkunft je Label (#77), für die farbigen Tags
            'farben' => fn (): array => Palette::listColors(),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
