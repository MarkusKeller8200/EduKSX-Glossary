<?php

namespace App\Http\Controllers;

use App\Glossary\FieldGuide;
use App\Glossary\PublicGlossary;
use App\Models\Term;
use App\Models\User;
use App\Support\AppVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/** Öffentliche Seiten ohne Anmeldung (PUB-01). */
class PageController extends Controller
{
    /** Startseite: veröffentlichte Begriffe mit Suche, A–Z und Kategorien (#24). */
    public function home(Request $request, PublicGlossary $glossary): SymfonyResponse
    {
        $user = $this->currentUserOrNull($request);

        return $this->personal(Inertia::render('home', ['begriffe' => $glossary->list($user)])->toResponse($request), $user);
    }

    /**
     * Detailseite eines veröffentlichten Begriffs (Direktlink, PUB-03, PUB-07). Adressen zusammengeführter Begriffe
     * leiten dauerhaft auf den verbleibenden Begriff weiter (#16).
     */
    public function term(Request $request, string $slug, PublicGlossary $glossary): SymfonyResponse
    {
        $user = $this->currentUserOrNull($request);
        $term = $glossary->find($slug, $user);
        if ($term === null) {
            $target = Term::query()->where('slug', $slug)->whereNotNull('merged_into_id')->first()?->mergeTarget();
            abort_if($target === null || $glossary->find($target->slug) === null, 404);

            return redirect("/begriffe/{$target->slug}", 301);
        }

        return $this->personal(Inertia::render('term', ['begriff' => $term])->toResponse($request), $user);
    }

    /** Angemeldet enthält die Antwort die persönliche Darstellung (DEC-020): nie zwischenspeichern. */
    private function personal(SymfonyResponse $response, ?User $user): SymfonyResponse
    {
        if ($user !== null) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    /** Hilfe: Felder eines Begriffs (#135). */
    public function help(): Response
    {
        return Inertia::render('help', ['felder' => FieldGuide::FIELDS, 'weiteres' => FieldGuide::EXTRAS]);
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
