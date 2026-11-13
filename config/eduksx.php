<?php

// Konfiguration von eduksx/core (#334), aus vendor/eduksx/core/config/eduksx.php; Modulschlüssel fest «glossary».

return [
    /*
    | Modulschlüssel aus dem Modulregister (EduKSX-Architektur docs/REGISTER-MODULE.md): englisch, Kleinbuchstaben,
    | ohne Bindestrich, nie geändert. Benennt Fehlertypen, Tokens, Ereignisse und MCP-Werkzeuge (API-Standard G2).
    */
    'module' => 'glossary',

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
