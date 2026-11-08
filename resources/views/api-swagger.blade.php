<!DOCTYPE html>
<html lang="de-CH">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>API ausprobieren (Swagger) – EduKSX-Glossary</title>
        <meta name="description" content="Swagger-Seite der REST-API des EduKSX-Glossary: Endpunkte direkt ausprobieren (OpenAPI 3.1).">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @include('partials.api-style')
    </head>
    <body>
        @include('partials.api-head')

        <noscript>Die Swagger-Seite braucht JavaScript. Die Beschreibung der API steht als
            <a href="/api/openapi.json">OpenAPI-Datei</a> zur Verfügung.</noscript>

        <p class="api-note api-info" role="note">
            «Try it out» sendet echte Anfragen an diese Instanz. Ein Token (Servicekonto) unter «Authorize» bleibt nur
            in diesem Browserfenster und wird nicht gespeichert. Schreibende Endpunkte (Vorschläge) landen in der
            Moderation.
        </p>

        <div id="swagger-ui"></div>

        {{-- Swagger UI (Apache-2.0), selbst ausgeliefert über Vite (#157) --}}
        @vite('resources/js/swagger.ts')
    </body>
</html>
