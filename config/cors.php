<?php

/*
 * CORS: Die öffentliche Lese-API darf von jeder Website abgerufen werden (nur GET, ohne Cookies).
 * «Authorization» für Token eines Servicekontos (z. B. Organisations-API aus Browser-Anwendungen, #159); weiterhin
 * ohne Cookies. Schreibende Endpunkte (Vorschläge) nur von Servern bzw. gleicher Herkunft (docs/KONZEPT-API.md).
 */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'If-None-Match', 'X-Request-Id'],
    'exposed_headers' => ['ETag', 'EduKSX-Release', 'X-Glossary-Release', 'Retry-After', 'X-Request-Id'],
    'max_age' => 86400,
    'supports_credentials' => false,
];
