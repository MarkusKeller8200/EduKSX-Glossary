{{-- Kopf und Nutzungshinweis der API-Seiten (/api/docs, /api/swagger) --}}
        {{-- Kopf mit Wortmarke nach CI MarKusSXCH (Segmente dekorativ, Name für Bildschirmleser) --}}
        <header class="api-head">
            <a href="/" class="wm" aria-label="EduKSX-Glossary – zur Website"><span aria-hidden="true"><span class="v">E</span>du<span class="o">K</span><span class="b">SX</span>-Glossary</span></a>
            <nav aria-label="API">
                <a href="/api/docs">Dokumentation</a> ·
                <a href="/api/swagger">Swagger (ausprobieren)</a> ·
                <a href="/api/postman.json" download>Postman</a> ·
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

