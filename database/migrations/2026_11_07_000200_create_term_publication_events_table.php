<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change-Feed für den Sync (#25, docs/KONZEPT-API.md): jede Veröffentlichung und jeder Rückzug eines Begriffs als
 * Ereignis mit fortlaufender Nummer (Cursor). Abnehmer holen /api/v1/changes?since=<cursor>.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_publication_events', function (Blueprint $table) {
            $table->id();                                       // Cursor, streng steigend
            $table->uuid('term_id')->index();                   // kein Fremdschlüssel: Ereignisse bleiben
            $table->string('action', 20);                       // published, unpublished
            $table->unsignedInteger('revision')->nullable();    // veröffentlichte Revision
            $table->foreignId('release_id')->nullable()->constrained('publication_releases')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_publication_events');
    }
};
