<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use EduKSX\Core\Http\ProblemException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprache der API (API-Standard A4, #326): `?locale=fr` vor der Kopfzeile `Accept-Language`; `?lang=` gilt
 * übergangsweise weiter. Ohne Angabe Deutsch (Grundsprache, Ausgabe wie bisher). Setzt die App-Sprache – damit
 * kommen auch Fehlertitel übersetzt (eduksx/core) – sowie `Content-Language` und `Vary: Accept-Language`.
 *
 * Die verlangte Übersetzungssprache steht im Request-Attribut `glossary.locale` (null = Deutsch, unverändert).
 */
final class ApiLocale
{
    public const ATTRIBUTE = 'glossary.locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::resolve($request);
        $request->attributes->set(self::ATTRIBUTE, $locale === Locale::De->value ? null : $locale);
        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);
        $vary = array_filter(array_map('trim', explode(',', (string) $response->headers->get('Vary', ''))));
        if (! in_array('Accept-Language', $vary, true)) {
            $vary[] = 'Accept-Language';
        }
        $response->headers->set('Vary', implode(', ', $vary));

        return $response;
    }

    /** @throws ProblemException 422 bei unbekannter Sprache im Parameter */
    public static function resolve(Request $request): string
    {
        foreach (['locale', 'lang'] as $parameter) {
            $value = $request->query($parameter);
            if ($value === null || $value === '') {
                continue;
            }
            $value = is_string($value) ? strtolower(trim($value)) : '';
            if (Locale::tryFrom($value) === null) {
                // Fehler auf Deutsch, die Sprache ist ja unbekannt
                app()->setLocale(Locale::De->value);
                throw new ProblemException(422, 'invalid_locale', 'Unbekannte Sprache «'.$value.'». Erlaubt: '.implode(', ', Locale::values()).'.', [], false, [$parameter => ['Erlaubt: '.implode(', ', Locale::values()).'.']]);
            }

            return $value;
        }

        return self::fromHeader((string) $request->headers->get('Accept-Language', ''));
    }

    /** Beste unterstützte Sprache aus Accept-Language (q-Werte, Regionen wie de-CH); sonst Deutsch. */
    private static function fromHeader(string $header): string
    {
        $candidates = [];
        foreach (explode(',', $header) as $i => $part) {
            [$tag, $params] = array_pad(explode(';', trim($part), 2), 2, '');
            $q = preg_match('/q=([0-9.]+)/', $params, $m) === 1 ? (float) $m[1] : 1.0;
            $primary = strtolower(substr(trim($tag), 0, 2));
            if ($q > 0 && Locale::tryFrom($primary) !== null) {
                $candidates[] = [$q, -$i, $primary];
            }
        }
        if ($candidates === []) {
            return Locale::De->value;
        }
        rsort($candidates);

        return $candidates[0][2];
    }
}
