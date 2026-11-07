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

        <noscript>Die interaktive Dokumentation braucht JavaScript. Die Beschreibung der API steht als
            <a href="/api/openapi.json">OpenAPI-Datei</a> zur Verfügung.</noscript>

        <div id="api-reference"></div>

        {{-- Scalar API Reference (wie die API-Seite im Intranet), hier über das CDN jsDelivr eingebunden --}}
        <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference@1"></script>
        <script>
            Scalar.createApiReference('#api-reference', {
                url: '/api/openapi.json',
                withDefaultFonts: false,
                hideDownloadButton: false,
                // Entwickler-Leiste von Scalar (nur auf localhost sichtbar, Teilen über die Scalar Registry) aus
                showDeveloperTools: 'never',
                defaultHttpClient: { targetKey: 'shell', clientKey: 'curl' },
                metaData: { title: 'API – EduKSX-Glossary' },
            });
        </script>
    </body>
</html>
