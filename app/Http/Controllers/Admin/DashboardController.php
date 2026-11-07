<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/** Startseite der Administration (nur angemeldet, Recht «administer»). Redaktion folgt in 0.2. */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'admins' => User::query()->where('is_admin', true)->count(),
        ]);
    }
}
