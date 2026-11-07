<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doppelte zusammenführen (#16, docs/KONZEPT-DATENMODELL.md): zusammengeführte Begriffe bleiben als Weiterleitung
 * bestehen (alte ID und alter Slug zeigen auf den verbleibenden Begriff); «als mehrdeutig belassen» wird je Gruppe
 * gemerkt, damit dieselbe Gruppe nicht immer wieder vorgeschlagen wird.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->uuid('merged_into_id')->nullable()->index();
            $table->timestamp('merged_at')->nullable();
        });

        Schema::table('term_publication_events', function (Blueprint $table) {
            $table->uuid('merged_into')->nullable();            // bei action «merged»: verbleibender Begriff
        });

        Schema::create('duplicate_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('signature', 64)->unique();          // Art, Wert und Mitglieder der Gruppe
            $table->string('kind', 20);                         // abbreviation, name, both
            $table->string('value');
            $table->json('term_ids');
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_decisions');
        Schema::table('term_publication_events', fn (Blueprint $table) => $table->dropColumn('merged_into'));
        Schema::table('terms', function (Blueprint $table) {
            $table->dropIndex(['merged_into_id']);
            $table->dropColumn(['merged_into_id', 'merged_at']);
        });
    }
};
