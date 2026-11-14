<?php

// Konfiguration von eduksx/core (#334), aus vendor/eduksx/core/config/eduksx.php; Modulschlüssel fest «glossary».

return [
    /*
    | Modulschlüssel aus dem Modulregister (EduKSX-Architektur docs/REGISTER-MODULE.md): englisch, Kleinbuchstaben,
    | ohne Bindestrich, nie geändert. Benennt Fehlertypen, Tokens, Ereignisse und MCP-Werkzeuge (API-Standard G2).
    */
    'module' => 'glossary',

    /*
    | Version für Steckbrief, /health und Historie: leer = Datei VERSION (wie config('glossary.version')).
    */
    'version' => env('EDUKSX_VERSION'),

    /*
    | Steckbrief GET /.well-known/eduksx-module.json (Plattform S5, #189). Nur, was das Glossary heute wirklich bietet:
    | Scopes nach Standard folgen mit #323, Rollen für Ident mit #186, Plugin-Abschnitt «ui» mit der Plugin-Probe.
    */
    'manifest' => [
        'name' => 'EduKSX-Glossary',
        'operation_mode' => 'central',
        'api_versions' => ['v1'],
        'scopes_offered' => [],
        'scopes_required' => [],
        'roles' => [],
        'events_emitted' => [],
        'events_consumed' => ['ident.user.deleted', 'ident.user.disabled'],
        'classification' => 'internal',
        'data_license' => 'CC-BY-4.0',
        // Plugins für Gastgeber (#338, KONZEPT-PLUGINS): Web-Komponenten, Kachel «Suche», Suche für die Gastgeber-Suche
        'ui' => [
            'elements' => '/ui/v1/glossary.js',
            'widgets' => [
                ['id' => 'term-search', 'title' => 'Glossary-Suche', 'element' => 'glossary-search', 'audience' => 'public'],
            ],
            'menu' => [
                ['title' => 'Glossary', 'url' => '/'],
            ],
            'search' => ['url' => '/api/v1/terms?q={query}', 'audience' => 'public'],
        ],
        'urls' => [
            'openapi' => '/api/openapi.json',
            'docs' => '/api/docs',
            'security_contact' => 'https://github.com/MarkusKeller8200/EduKSX-Glossary/security',
        ],
    ],

    /*
    | Bereitschaft GET /health (S5): database und migrations kritisch (503), queue und storage → degraded.
    */
    'health' => [
        'checks' => ['database', 'migrations', 'queue', 'storage'],
        'critical' => ['database', 'migrations'],
    ],

    'routes' => [
        'manifest' => true,
        'health' => true,
    ],

    /*
    | Ereignisse von Ident (S4, #187): aktiv, sobald EDUKSX_EVENTS_KEYS gesetzt ist (Schlüssel von Ident, mehrere
    | durch Komma beim Wechsel). EDUKSX_EVENTS_SOURCES begrenzt auf die Adresse von Ident.
    */
    'events' => [
        'path' => 'api/v1/platform-events',
        'keys' => array_values(array_filter(explode(',', (string) env('EDUKSX_EVENTS_KEYS', '')))),
        'sources' => array_values(array_filter(explode(',', (string) env('EDUKSX_EVENTS_SOURCES', '')))),
        'tolerance' => 300,
        'max_bytes' => 262144,
        'rate_limit' => 600,
        'retention_days' => 30,
    ],

    /*
    | Basis der Fehlertypen (API-Standard A6). Gemeinsame Typen: {base}/{code}, modul-eigene: {base}/{module}/{code}.
    */
    'problem_base_url' => env('EDUKSX_PROBLEM_BASE_URL', 'https://docs.eduksx.ch/problems'),

    /*
    | Pfade, deren Fehler als application/problem+json kommen (Request::is-Muster).
    */
    'api_paths' => ['api/*'],

    /*
    | Abruflimit «api» je Minute (API-Standard C5): je Token, sonst je Adresse.
    */
    'rate_limit' => [
        'api' => (int) env('EDUKSX_RATE_LIMIT_API', 120),
    ],

    /*
    | Sicherheitskopfzeilen (API-Standard C6, Plattform §6). frame-src ergänzen nur, wenn das Modul etwas einbettet.
    */
    'security_headers' => [
        'frame_src' => ["'self'"],
        'hsts' => 'max-age=31536000; includeSubDomains',
    ],

    /*
    | CORS-Voreinstellung (API-Standard C6), gilt, solange das Modul keine eigene config/cors.php hat:
    | öffentliche GET-Endpunkte für alle Herkünfte, ohne Cookies. Schreibende Endpunkte: eigene config/cors.php mit
    | einer Liste von Herkünften je Instanz, nie «*» mit Anmeldedaten.
    */
    'cors' => [
        'paths' => ['api/*'],
        'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],
        'allowed_origins' => ['*'],
        'allowed_origins_patterns' => [],
        'allowed_headers' => ['Accept', 'Accept-Language', 'Authorization', 'Content-Type', 'If-None-Match', 'X-Request-Id'],
        'exposed_headers' => ['ETag', 'EduKSX-Release', 'Retry-After', 'X-Request-Id'],
        'max_age' => 86400,
        'supports_credentials' => false,
    ],
];
