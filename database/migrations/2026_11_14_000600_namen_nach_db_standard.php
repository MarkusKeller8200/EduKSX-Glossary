<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Namen nach DB-Standard A1 (#330): gleiche Namen in Datenbank, API und Export.
 *
 * abwaertskompatibel: Umbenennen in einem Schritt statt erweitern/aufräumen (U18). Grund: Updates laufen heute mit
 * kurzem Unterbruch (alte und neue Version nie gleichzeitig; blau/grün folgt mit #184); ein Rücksprung auf 0.13 geht
 * nur mit der Sicherung vor dem Update. Gespeicherte Revisionen und Vorschläge mit dem alten Schlüssel
 * `lifecycle_status` liest der Code weiter.
 */
return new class extends Migration
{
    /** Tabelle => [alt => neu] */
    private const COLUMNS = [
        'terms' => ['lifecycle_status' => 'status'],
        'term_aliases' => ['language' => 'locale'],
        'user_identities' => ['tenant_id' => 'entra_tenant_id'],
        'proposals' => ['user_id' => 'author_id'],
        'import_batches' => ['user_id' => 'imported_by_id'],
        'term_publication_events' => ['release_id' => 'publication_release_id'],
        'user_audit' => ['old' => 'old_values', 'new' => 'new_values'],
    ];

    public function up(): void
    {
        // abwaertskompatibel: Update mit Unterbruch bis #184, Rücksprung nur mit Sicherung (siehe oben)
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $old => $new) {
                    $t->renameColumn($old, $new);
                }
            });
        }
        Schema::rename('organization_term_overrides', 'term_organization_overrides');
    }

    public function down(): void
    {
        Schema::rename('term_organization_overrides', 'organization_term_overrides');
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $old => $new) {
                    $t->renameColumn($new, $old);
                }
            });
        }
    }
};
