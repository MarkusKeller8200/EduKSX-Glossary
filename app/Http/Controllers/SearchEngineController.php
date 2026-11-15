<?php

namespace App\Http\Controllers;

use App\Models\Term;
use Illuminate\Http\Response;

/**
 * robots.txt und sitemap.xml je Instanz (#393). Indexiert wird nur mit GLOSSARY_SUCHMASCHINEN=true (prod); sonst
 * sperrt robots.txt alles und es gibt keine Sitemap. Adressen aus APP_URL, damit jede Instanz auf sich selbst zeigt.
 */
class SearchEngineController extends Controller
{
    /** Öffentliche Seiten ohne Begriffe, mit Priorität */
    private const PAGES = [
        '/' => '1.0',
        '/hilfe' => '0.5',
        '/entwickler' => '0.5',
        '/versionen' => '0.3',
        '/legals' => '0.2',
        '/datenschutz' => '0.2',
    ];

    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        if (config('glossary.search_engines')) {
            $lines[] = 'Allow: /';
            foreach (['/admin', '/anmelden', '/registrieren', '/konto', '/passwort-vergessen', '/passwort-zuruecksetzen'] as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.$this->base().'/sitemap.xml';
        } else {
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemap(): Response
    {
        abort_unless((bool) config('glossary.search_engines'), 404);

        $base = $this->base();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach (self::PAGES as $path => $priority) {
            $xml .= $this->url($base.($path === '/' ? '/' : $path), null, $priority);
        }
        // Veröffentlichte, aktive Begriffe – wie die Detailseite (PublicGlossary::find)
        $terms = Term::query()->published()->with('publishedRevision')->orderBy('slug')->get();
        foreach ($terms as $term) {
            $revision = $term->publishedRevision;
            if ($revision === null || ($revision->snapshot['is_active'] ?? true) !== true) {
                continue;
            }
            $xml .= $this->url($base.'/begriffe/'.rawurlencode((string) $term->slug), $revision->published_at?->toDateString(), '0.8');
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function url(string $loc, ?string $lastmod, string $priority): string
    {
        return '  <url><loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'
            .($lastmod !== null ? '<lastmod>'.$lastmod.'</lastmod>' : '')
            .'<priority>'.$priority.'</priority></url>'."\n";
    }

    private function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
