<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AppVersion;
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
            ],
            'appVersion' => AppVersion::info(),
            'links' => [
                'githubRepo' => config('glossary.github_repo'),
                'pages' => config('glossary.pages_url'),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
