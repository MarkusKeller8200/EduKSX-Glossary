<?php

namespace App\Http\Controllers;

use App\Console\Commands\AiGuide;
use App\Glossary\ExcelFormat;
use App\Glossary\FieldGuide;
use App\Glossary\Permalink;
use App\Glossary\PublicGlossary;
use App\Models\Role;
use App\Models\Term;
use App\Models\User;
use App\Support\AppVersion;
use App\Support\DataModel;
use App\Support\Download;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

    /**
     * Permalink /g/{code} (#154, DEC-026): leitet auf die Seite des Begriffs; zusammengeführte Begriffe auf den
     * verbleibenden. Nur veröffentlichte, öffentlich sichtbare Begriffe; sonst 404.
     */
    public function permalink(string $code, PublicGlossary $glossary): RedirectResponse
    {
        $normalized = Permalink::normalize($code);
        $term = $normalized === null ? null : Term::query()->where('code', $normalized)->first();
        if ($term !== null && $term->merged_into_id !== null) {
            $term = $term->mergeTarget();
        }
        abort_if($term === null || $glossary->find($term->slug) === null, 404);

        return redirect("/begriffe/{$term->slug}", 302);
    }

    /** Angemeldet enthält die Antwort die persönliche Darstellung (DEC-020): nie zwischenspeichern. */
    private function personal(SymfonyResponse $response, ?User $user): SymfonyResponse
    {
        if ($user !== null) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    /** Für Entwickler (#155): Einbindung per Script oder <glossary-term>, Daten per API. */
    /** Mit KI recherchieren (#481): Anleitung aus docs/KI-ANLEITUNG.md, Prompt zum Kopieren, Vorlage. */
    public function aiGuide(): Response
    {
        $markdown = (string) @file_get_contents(base_path(AiGuide::PATH));

        return Inertia::render('ki-anleitung', [
            // eigene Datei; HTML darin wird entfernt, unsichere Links nicht zugelassen
            'html' => (string) Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'prompt' => AiGuide::prompt($markdown),
        ]);
    }

    /** Leere Vorlage mit allen Spalten des Excel-Formats (#481). */
    public function aiTemplate(): SymfonyResponse
    {
        return Download::table('xlsx', ExcelFormat::header(), [], 'glossary-vorlage');
    }

    /** Anleitung als Markdown zum Weitergeben an einen KI-Assistenten (#481). */
    public function aiGuideMarkdown(): SymfonyResponse
    {
        return response((string) @file_get_contents(base_path(AiGuide::PATH)), 200, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    public function developers(DataModel $model): Response
    {
        $manifest = json_decode((string) @file_get_contents(public_path('embed/manifest.json')), true);

        return Inertia::render('developers', [
            'origin' => rtrim(url('/'), '/'),
            // MCP-Server (#261): nur anzeigen, wenn eingeschaltet
            'mcp' => Settings::bool('mcp.enabled') ? url('/mcp') : null,
            'einbindung' => is_array($manifest) ? [
                'version' => (string) ($manifest['version'] ?? ''),
                'datei' => (string) ($manifest['file'] ?? ''),
                'neueste' => (string) ($manifest['latest'] ?? ''),
                'integrity' => (string) ($manifest['integrity'] ?? ''),
            ] : null,
            // Datenmodell (#170): Tabellen, ER-Diagramm, SQL-Script zum Herunterladen
            'datenmodell' => $model->tables(),
            'diagramm' => $model->mermaid(),
            // Browsererweiterung (#385): ZIP am neuesten Release, gebaut von .github/workflows/extension.yml
            'erweiterung' => self::extensionLinks(),
        ]);
    }

    /**
     * Download der Browsererweiterung (#385) am neuesten Release des Quellcodes; leer, wenn keine Adresse eingestellt
     * ist oder sie nicht auf GitHub zeigt.
     *
     * @return array{download: string, releases: string}|null
     */
    private static function extensionLinks(): ?array
    {
        $base = rtrim(Settings::string('links.source_url'), '/');
        if (preg_match('#^https://github\.com/[^/]+/[^/]+$#', $base) !== 1) {
            return null;
        }

        return [
            'download' => $base.'/releases/latest/download/eduksx-glossary-extension.zip',
            'releases' => $base.'/releases/latest',
        ];
    }

    /** Datenmodell als SQL-Script (#170), nur Struktur. */
    public function schemaSql(DataModel $model): SymfonyResponse
    {
        return response($model->sql(), 200, [
            'Content-Type' => 'application/sql; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="eduksx-glossary-datenmodell.sql"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** Hilfe: Felder eines Begriffs (#135). */
    public function help(Request $request): Response
    {
        // Reiter je Rolle (#166): Admin und Developer nur mit dieser Rolle
        $roles = $this->currentUserOrNull($request)?->roleKeys() ?? [];
        $developer = in_array(Role::DEVELOPER, $roles, true);
        $tabs = array_filter(FieldGuide::TABS, fn (string $label, string $key): bool => match ($key) {
            'admin' => $developer || in_array(Role::ADMIN, $roles, true),
            'developer' => $developer,
            default => true,
        }, ARRAY_FILTER_USE_BOTH);

        $fields = array_map(fn (array $f): array => $f + ['tab' => FieldGuide::GROUP_TABS[$f['gruppe']] ?? 'benutzer'], FieldGuide::FIELDS);

        return Inertia::render('help', [
            'reiter' => array_map(fn (string $key, string $label): array => ['key' => $key, 'label' => $label], array_keys($tabs), array_values($tabs)),
            // Felder gehören zu den Reitern Gast/Benutzer, Organisations-Admin und Moderator (immer sichtbar)
            'felder' => array_values($fields),
            'weiteres' => array_values(array_filter(FieldGuide::EXTRAS, fn (array $e): bool => isset($tabs[$e['tab']]))),
        ]);
    }

    /** Rechtliches, verwendete Quellprojekte und Drittlizenzen (Prüfstand). */
    public function legals(): Response
    {
        return Inertia::render('legals');
    }

    /** Impressum (#411): Angaben je Instanz aus den Einstellungen (Administration › Einstellungen › Impressum). */
    public function imprint(): Response
    {
        $email = Settings::string('imprint.email');

        return Inertia::render('imprint', [
            'impressum' => [
                'betreiber' => Settings::string('imprint.operator'),
                'adresse' => Settings::string('imprint.address'),
                'email' => $email,
                'datenschutz' => Settings::string('imprint.privacy_email') ?: $email,
                'register' => Settings::string('imprint.register'),
                'inhalt' => Settings::string('imprint.content'),
            ],
        ]);
    }

    /** Versionshistorie aus CHANGELOG.md (docs/KONZEPT-VERSIONIERUNG.md, #6). */
    public function versions(): Response
    {
        return Inertia::render('versions', [
            'releases' => AppVersion::changelog(),
        ]);
    }
}
