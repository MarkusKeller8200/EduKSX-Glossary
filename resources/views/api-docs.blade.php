<!DOCTYPE html>
<html lang="de-CH">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>API – EduKSX-Glossary</title>
        <meta name="description" content="Interaktive Dokumentation der öffentlichen REST-API des EduKSX-Glossary (OpenAPI 3.1).">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <style>
            body { margin: 0; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
            .api-head { display: flex; flex-wrap: wrap; gap: .75rem 1.5rem; align-items: center; justify-content: space-between;
                padding: .75rem 1rem; border-bottom: 1px solid #e2e8f0; background: #fbfbfc; color: #222; font-size: .9rem; }
            .api-head a { color: #0272ad; }
            .wm { font-weight: 600; font-size: 1.1rem; color: #222; text-decoration: none; }
            .wm .v { color: #7952b3; } .wm .o { color: #ff8a00; } .wm .b { color: #007ebb; }
            .api-note { margin: 0; padding: .5rem 1rem; background: #fff3e5; color: #5c3200; font-size: .85rem; }
            .api-note a { color: inherit; }
            noscript { display: block; padding: 2rem 1rem; }
        </style>
    </head>
    <body>
        {{-- Kopf mit Wortmarke nach CI MarKusSXCH (Segmente dekorativ, Name für Bildschirmleser) --}}
        <header class="api-head">
            <a href="/" class="wm" aria-label="EduKSX-Glossary – zur Website"><span aria-hidden="true"><span class="v">E</span>du<span class="o">K</span><span class="b">SX</span>-Glossary</span></a>
            <nav aria-label="API">
                <a href="/api/openapi.json">OpenAPI (JSON)</a> ·
                <a href="/api/openapi.yaml">YAML</a> ·
                <a href="/api/v1">/api/v1</a> ·
                <a href="/">Website</a>
            </nav>
        </header>

        {{-- Nutzung und Stabilität (DEC-016, #30) --}}
        <p class="api-note" role="note">
            @if (config('glossary.api_stability') === 'preview')
                <strong>Vorschau:</strong> Bis zum öffentlichen Betrieb können sich Felder und Endpunkte noch ändern
                (Hinweise im <a href="/versionen">Versionsverlauf</a>). Danach gilt: v1 bleibt rückwärtskompatibel.
            @else
                <strong>Stabil:</strong> v1 bleibt rückwärtskompatibel; Brüche nur in einer neuen Version mit
                mindestens 12 Monaten Parallelbetrieb.
            @endif
            Ohne Schlüssel, 120 Abrufe pro Minute, ohne Zusage zur Verfügbarkeit.
            Daten unter <a href="{{ config('glossary.license.url') }}">{{ config('glossary.license.name') }}</a>
            – Nennung «{{ config('glossary.license.attribution') }}».
        </p>

        <noscript>Die interaktive Dokumentation braucht JavaScript. Die Beschreibung der API steht als
            <a href="/api/openapi.json">OpenAPI-Datei</a> zur Verfügung.</noscript>

        <div id="api-reference"></div>

        {{-- Scalar API Reference (wie die API-Seite im Intranet), selbst ausgeliefert über Vite (#44) --}}
        @vite('resources/js/api-docs.ts')
    </body>
</html>
