<?php

namespace App\Http\Controllers;

use App\Glossary\PublicGlossary;
use App\Support\AppVersion;
use Inertia\Inertia;
use Inertia\Response;

/** Öffentliche Seiten ohne Anmeldung (PUB-01). */
class PageController extends Controller
{
    /** Startseite: veröffentlichte Begriffe mit Suche, A–Z und Kategorien (#24). */
    public function home(PublicGlossary $glossary): Response
    {
        return Inertia::render('home', ['begriffe' => $glossary->list()]);
    }

    /** Detailseite eines veröffentlichten Begriffs (Direktlink, PUB-03, PUB-07). */
    public function term(string $slug, PublicGlossary $glossary): Response
    {
        $term = $glossary->find($slug);
        abort_if($term === null, 404);

        return Inertia::render('term', ['begriff' => $term]);
    }

    /** Versionshistorie aus CHANGELOG.md (docs/KONZEPT-VERSIONIERUNG.md, #6). */
    public function versions(): Response
    {
        return Inertia::render('versions', [
            'releases' => AppVersion::changelog(),
        ]);
    }
}
