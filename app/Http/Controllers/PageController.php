<?php

namespace App\Http\Controllers;

use App\Support\AppVersion;
use Inertia\Inertia;
use Inertia\Response;

/** Öffentliche Seiten ohne Anmeldung (PUB-01). */
class PageController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('home');
    }

    /** Versionshistorie aus CHANGELOG.md (docs/KONZEPT-VERSIONIERUNG.md, #6). */
    public function versions(): Response
    {
        return Inertia::render('versions', [
            'releases' => AppVersion::changelog(),
        ]);
    }
}
