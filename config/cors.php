<?php

/*
 * CORS: Die öffentliche Lese-API darf von jeder Website abgerufen werden (nur GET, ohne Cookies).
 * Schreibende Endpunkte kommen später mit Anmeldung und eigener Regel (docs/KONZEPT-API.md).
 */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'If-None-Match'],
    'exposed_headers' => ['ETag', 'X-Glossary-Release', 'Retry-After'],
    'max_age' => 86400,
    'supports_credentials' => false,
];
