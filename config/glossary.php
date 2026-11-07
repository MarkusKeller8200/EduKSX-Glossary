<?php

/*
 * EduKSX-Glossary: Version, Build und Einstellungen (docs/KONZEPT-VERSIONIERUNG.md).
 */

return [
    // Versionsnummer aus der Datei VERSION (nur bei freigegebenem Release geändert)
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0',

    // Vom Deployment gesetzt: Commit, Zeitpunkt, `git describe --tags --long` (Developer Release)
    'build' => env('GLOSSARY_BUILD'),
    'build_date' => env('GLOSSARY_BUILD_DATE'),
    'build_describe' => env('GLOSSARY_BUILD_DESCRIBE'),

    // Repository für «Fehler melden» / «Idee vorschlagen» (vorausgefüllte Issue-Formulare); leer = Knöpfe ausblenden
    'github_repo' => env('GLOSSARY_GITHUB_REPO', 'MarkusKeller8200/EduKSX-Glossary'),

    // Lizenz der Begriffssammlung (DEC-016, #30): vorerst CC BY 4.0, Nennung wie angegeben
    'license' => [
        'name' => 'CC BY 4.0',
        'url' => 'https://creativecommons.org/licenses/by/4.0/deed.de',
        'attribution' => 'EduKSX-Glossary, glossary.eduksx.ch',
    ],

    // API v1 bis zum öffentlichen Betrieb als Vorschau (DEC-016): Felder können sich noch ändern
    'api_stability' => env('GLOSSARY_API_STABILITY', 'preview'),

    // Datenschutzerklärung: Version, deren Kenntnisnahme bei Registrierung bzw. Änderung gespeichert wird (#41, #42)
    'privacy_version' => env('GLOSSARY_PRIVACY_VERSION', '1.0-entwurf'),

    // Spam-Schutz für Formulare (Cloudflare Turnstile, #41); ohne Schlüssel ausgeschaltet (lokal)
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
    ],

    // Reduzierte Fassung auf GitHub Pages (docs/KONZEPT-HOSTING.md)
    'pages_url' => env('GLOSSARY_PAGES_URL', 'https://markuskeller8200.github.io/EduKSX-Glossary/'),
];
