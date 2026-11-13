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
    // Frühere Adresse, die auf APP_URL weiterleitet (#304), z. B. glossary.markussx.ch nach dem Wechsel auf glossary-dev
    'redirect_host' => (string) env('GLOSSARY_REDIRECT_HOST', ''),

    // Selbstregistrierung (#302): Vorgabe des Schalters in den Einstellungen; wirkt nur mit eingerichtetem Versand
    'registration_open' => (bool) env('GLOSSARY_REGISTRATION_OPEN', false),

    // Papierkorb (#286): Tage bis zur endgültigen Löschung; einstellen nur im Betrieb (Super-Admin)
    'trash_days' => (int) env('GLOSSARY_TRASH_DAYS', 30),

    'privacy_version' => env('GLOSSARY_PRIVACY_VERSION', '1.0-entwurf'),

    // Datenschutzerklärung noch Entwurf (Hinweis auf /datenschutz), bis der Freigabecheck erledigt ist (#42)
    'privacy_draft' => (bool) env('GLOSSARY_PRIVACY_DRAFT', true),

    // Löschfristen (docs/LOESCHKONZEPT.md, #42)
    'retention' => [
        'inactive_months' => (int) env('GLOSSARY_RETENTION_INACTIVE_MONTHS', 24),
        'notice_days' => 30,
        'audit_years' => (int) env('GLOSSARY_RETENTION_AUDIT_YEARS', 5),
    ],

    // Spam-Schutz für Formulare (Cloudflare Turnstile, #41); ohne Schlüssel ausgeschaltet (lokal)
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
    ],

    // Reduzierte Fassung auf GitHub Pages (docs/KONZEPT-HOSTING.md)
    'pages_url' => env('GLOSSARY_PAGES_URL', 'https://markuskeller8200.github.io/EduKSX-Glossary/'),

    // Mail-Versand (#219): mit MAIL_MAILER in der Umgebung gilt diese, die Einstellung in der Administration ist gesperrt
    'mail_env' => env('MAIL_MAILER') !== null,

    // Updates (#176, #177, #178): Hinweis auf neue Versionen (GitHub-Releases), Quelle der Releases
    'update_check' => (bool) env('GLOSSARY_UPDATE_CHECK', true),
    'update_repo' => env('GLOSSARY_UPDATE_REPO', 'MarkusKeller8200/EduKSX-Glossary'),
    // Zusätzlicher Schlüssel für «Jetzt aktualisieren» (#177); leer = nur Recht «system» mit zweitem Faktor
    'update_key' => env('GLOSSARY_UPDATE_KEY', ''),
    // Vollständiger Dump (#268): Passwort vorschreiben (Vorgabe aus, später an); Passwort für den Befehl ohne Eingabe
    'dump_require_password' => (bool) env('GLOSSARY_DUMP_REQUIRE_PASSWORD', false),
    'dump_password' => env('GLOSSARY_DUMP_PASSWORD', ''),
];
