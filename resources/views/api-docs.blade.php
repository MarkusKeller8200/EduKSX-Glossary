<!DOCTYPE html>
<html lang="de-CH">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>API – EduKSX-Glossary</title>
        <meta name="description" content="Interaktive Dokumentation der öffentlichen REST-API des EduKSX-Glossary (OpenAPI 3.1).">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @include('partials.api-style')
    </head>
    <body>
        @include('partials.api-head')

        <noscript>Die interaktive Dokumentation braucht JavaScript. Die Beschreibung der API steht als
            <a href="/api/openapi.json">OpenAPI-Datei</a> zur Verfügung.</noscript>

        <div id="api-reference"></div>

        {{-- Scalar API Reference (wie die API-Seite im Intranet), selbst ausgeliefert über Vite (#44) --}}
        @vite('resources/js/api-docs.ts')
    </body>
</html>
