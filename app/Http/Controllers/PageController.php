<?php

namespace App\Http\Controllers;

use App\Glossary\PublicGlossary;
use App\Models\Term;
use App\Support\AppVersion;
use Illuminate\Http\RedirectResponse;
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

    /**
     * Detailseite eines veröffentlichten Begriffs (Direktlink, PUB-03, PUB-07). Adressen zusammengeführter Begriffe
     * leiten dauerhaft auf den verbleibenden Begriff weiter (#16).
     */
    public function term(string $slug, PublicGlossary $glossary): Response|RedirectResponse
    {
        $term = $glossary->find($slug);
        if ($term === null) {
            $target = Term::query()->where('slug', $slug)->whereNotNull('merged_into_id')->first()?->mergeTarget();
            abort_if($target === null || $glossary->find($target->slug) === null, 404);

            return redirect("/begriffe/{$target->slug}", 301);
        }

        return Inertia::render('term', ['begriff' => $term]);
    }

    /** Rechtliches, verwendete Quellprojekte und Drittlizenzen (Prüfstand). */
    public function legals(): Response
    {
        return Inertia::render('legals');
    }

    /** Versionshistorie aus CHANGELOG.md (docs/KONZEPT-VERSIONIERUNG.md, #6). */
    public function versions(): Response
    {
        return Inertia::render('versions', [
            'releases' => AppVersion::changelog(),
        ]);
    }
}
