<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datenmodell des Glossary (#12, docs/KONZEPT-DATENMODELL.md), SQLite.
 *
 * Drei Statusachsen getrennt: terms.lifecycle_status (aktuell/historisch/zu_pruefen), Moderation (später
 * proposals) und Veröffentlichung (terms.published_revision_id → unveränderliche Revision). Website und API zeigen
 * nur die veröffentlichte Revision, nie den Arbeitsstand in terms.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Listen: Kategorien und Herkunft (Felder wie im Intranet: Kürzel, Bezeichnung, Label, Beschreibung, URL, Notiz)
        foreach (['categories', 'sources'] as $list) {
            Schema::create($list, function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key', 50)->unique();          // stabiler Schlüssel, z. B. «beh», «vsa»
                $table->string('abbreviation', 30)->nullable();
                $table->string('name');
                $table->string('label', 60)->nullable();      // kurzes Label (Tag); leer = Kürzel
                $table->text('description')->nullable();
                $table->string('url', 500)->nullable();
                $table->text('note')->nullable();
                $table->string('color', 20)->nullable();      // Palette wie im Intranet
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::create('terms', function (Blueprint $table) {
            $table->uuid('id')->primary();                    // beim Excel-Import: ID aus dem Intranet (#13)
            $table->string('slug')->unique();
            $table->string('official_abbreviation', 60)->nullable();
            $table->string('own_abbreviation', 60)->nullable();
            $table->string('official_name')->nullable();
            $table->string('own_name')->nullable();
            $table->string('short_description', 160)->nullable();
            $table->text('description')->nullable();
            $table->text('note')->nullable();
            $table->string('url', 500)->nullable();
            $table->json('used_in')->nullable();              // «Intern genutzt in», Liste von Schlüsseln
            $table->text('search_terms')->nullable();
            $table->string('lifecycle_status', 20)->default('aktuell');
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('revision')->default(0);  // letzte Revision (Arbeitsstand)
            $table->uuid('published_revision_id')->nullable()->index();
            $table->timestamps();

            $table->index('official_abbreviation');
            $table->index('own_abbreviation');
        });

        // Schreibweisen: nicht eindeutig, weil mehrdeutige Abkürzungen erlaubt sind
        Schema::create('term_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $table->string('alias', 120)->index();
            $table->string('alias_type', 20);                 // official, own, search
            $table->string('language', 10)->default('de');
        });

        Schema::create('term_categories', function (Blueprint $table) {
            $table->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained()->restrictOnDelete();
            $table->primary(['term_id', 'category_id']);
        });

        Schema::create('term_sources', function (Blueprint $table) {
            $table->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('source_id')->constrained()->restrictOnDelete();
            $table->string('url', 500)->nullable();
            $table->date('retrieved_at')->nullable();
            $table->string('note')->nullable();
            $table->primary(['term_id', 'source_id']);
        });

        // Unveränderliche Abbilder je Änderung; veröffentlicht = published_at gesetzt
        Schema::create('term_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('change_type', 20);                // created, updated, published, unpublished, merged
            $table->json('snapshot');
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_label')->nullable();       // z. B. «Excel-Import»
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['term_id', 'revision']);
        });

        // Datenstand: jede Veröffentlichung erhöht die Nummer (Sync, ETag)
        Schema::create('publication_releases', function (Blueprint $table) {
            $table->id();                                     // Nummer des Datenstands
            $table->uuid('term_id')->nullable();              // auslösender Begriff
            $table->string('action', 20);                     // published, unpublished
            $table->char('checksum', 64);
            $table->unsignedInteger('term_count');
            $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_releases');
        Schema::dropIfExists('term_revisions');
        Schema::dropIfExists('term_sources');
        Schema::dropIfExists('term_categories');
        Schema::dropIfExists('term_aliases');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('categories');
    }
};
