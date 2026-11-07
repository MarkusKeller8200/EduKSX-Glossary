<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PublicationRelease;
use App\Models\Role;
use App\Models\Source;
use App\Models\Term;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/** Startseite der Administration (nur angemeldet, Zugang «administer»). */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'admins' => User::query()->whereHas('roles', fn ($q) => $q->whereIn('key', [Role::ADMIN, Role::DEVELOPER]))->count(),
            'stats' => [
                'terms' => Term::query()->current()->count(),
                'published' => Term::query()->published()->count(),
                'categories' => Category::query()->count(),
                'sources' => Source::query()->count(),
                'release' => PublicationRelease::query()->max('id'),
            ],
        ]);
    }
}
