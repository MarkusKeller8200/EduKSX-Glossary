<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Gemeinsame und persönliche Daten, Änderungsjournal (DEC-020, #80, docs/KONZEPT-DATENMODELL.md).
 *
 * - `own_abbreviation`, `own_name`, Aliase vom Typ `own` und `used_in` sind keine gemeinsamen Fachdaten mehr. Alle
 *   vorhandenen Werte werden vollständig nach `term_legacy_values` gesichert (je Wert eine Zeile) und danach aus
 *   `terms` bzw. `term_aliases` entfernt. Nichts wird automatisch einem Konto oder einem offiziellen Feld zugeordnet;
 *   die Redaktion ordnet die Werte auf der Seite «Altwerte» zu.
 * - `term_user_overrides`: persönliche Darstellung je Begriff und Konto (nur für die Person selbst).
 * - Journal: `term_revisions` mit `change_set` (Feld → alt/neu), `actor_type` und `context`; Ereignisse der
 *   Veröffentlichung mit Akteur (intern, nicht im Change-Feed).
 * - Historische Revisionen bleiben unverändert (auch ihre Abbilder mit Altfeldern).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_user_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('abbreviation_override', 60)->nullable();
            $table->string('name_override')->nullable();
            $table->string('short_description_override', 160)->nullable();
            $table->text('description_override')->nullable();
            $table->json('aliases_override')->nullable();
            $table->timestamps();
            $table->unique(['term_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('term_legacy_values', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $table->string('field', 30);                      // own_abbreviation, own_name, own_alias, used_in
            $table->string('value', 500);
            $table->string('origin', 20);                     // migration, import
            $table->string('resolution', 20)->nullable();     // official, alternative, search, discarded
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['term_id', 'field', 'value']);
        });

        Schema::table('term_revisions', function (Blueprint $table) {
            $table->json('change_set')->nullable();           // Feld → {old, new}
            $table->string('actor_type', 20)->nullable();     // community, moderator, admin, developer, service, system
            $table->json('context')->nullable();              // z. B. Quelle (Excel), Zusammenführung
        });

        Schema::table('term_publication_events', function (Blueprint $table) {
            $table->string('actor_type', 20)->nullable();
            $table->string('actor_label', 120)->nullable();
            $table->uuid('actor_id')->nullable();             // wird bei Kontolöschung geleert
        });

        // Altwerte sichern
        $now = now();
        $rows = [];
        foreach (DB::table('terms')->select(['id', 'own_abbreviation', 'own_name', 'used_in'])->orderBy('id')->get() as $term) {
            foreach (['own_abbreviation', 'own_name'] as $field) {
                $value = trim((string) $term->{$field});
                if ($value !== '') {
                    $rows[] = ['term_id' => $term->id, 'field' => $field, 'value' => $value];
                }
            }
            $used = json_decode((string) $term->used_in, true);
            foreach (is_array($used) ? $used : [] as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $rows[] = ['term_id' => $term->id, 'field' => 'used_in', 'value' => $value];
                }
            }
        }
        foreach (DB::table('term_aliases')->where('alias_type', 'own')->orderBy('id')->get() as $alias) {
            $rows[] = ['term_id' => $alias->term_id, 'field' => 'own_alias', 'value' => trim((string) $alias->alias)];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('term_legacy_values')->insertOrIgnore(array_map(
                fn (array $r): array => $r + ['origin' => 'migration', 'created_at' => $now],
                $chunk,
            ));
        }
        DB::table('term_aliases')->where('alias_type', 'own')->delete();

        Schema::table('terms', function (Blueprint $table) {
            $table->dropIndex(['own_abbreviation']);
        });
        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn(['own_abbreviation', 'own_name', 'used_in']);
        });
    }

    /** Rückweg: Spalten wieder anlegen und die bei der Migration gesicherten Werte zurückschreiben. */
    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->string('own_abbreviation', 60)->nullable()->index();
            $table->string('own_name')->nullable();
            $table->json('used_in')->nullable();
        });

        $legacy = DB::table('term_legacy_values')->where('origin', 'migration')->orderBy('id')->get()->groupBy('term_id');
        foreach ($legacy as $termId => $values) {
            $first = fn (string $field): ?string => $values->firstWhere('field', $field)?->value;
            $used = $values->where('field', 'used_in')->pluck('value')->values()->all();
            DB::table('terms')->where('id', $termId)->update([
                'own_abbreviation' => $first('own_abbreviation'),
                'own_name' => $first('own_name'),
                'used_in' => $used === [] ? null : json_encode($used, JSON_UNESCAPED_UNICODE),
            ]);
            foreach ($values->where('field', 'own_alias') as $alias) {
                DB::table('term_aliases')->insert([
                    'term_id' => $termId, 'alias' => Str::limit((string) $alias->value, 120, ''), 'alias_type' => 'own', 'language' => 'de',
                ]);
            }
        }

        Schema::table('term_publication_events', function (Blueprint $table) {
            $table->dropColumn(['actor_type', 'actor_label', 'actor_id']);
        });
        Schema::table('term_revisions', function (Blueprint $table) {
            $table->dropColumn(['change_set', 'actor_type', 'context']);
        });
        Schema::dropIfExists('term_legacy_values');
        Schema::dropIfExists('term_user_overrides');
    }
};
