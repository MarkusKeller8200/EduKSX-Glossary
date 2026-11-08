<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vorschläge und Moderation (#113, KONZEPT-DATENMODELL, DEC-022, DEC-024): neuer Begriff oder Korrektur mit
 * Basisrevision, Änderung als JSON, Status; Ereignisse (Rückfrage, Antwort, Entscheid). Nie öffentlich.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();                                               // Vorschlags-Nummer
            $table->string('kind', 10);                                 // new, change
            $table->foreignUuid('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->unsignedInteger('base_revision')->nullable();       // veröffentlichte Revision beim Einreichen
            $table->json('changes');                                    // Feld → vorgeschlagener Wert
            $table->text('reason')->nullable();                         // Begründung, Quelle
            $table->string('status', 20)->default('eingereicht')->index();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('service_account_id')->nullable()->constrained('service_accounts')->nullOnDelete();
            $table->string('author_label', 120);                        // Bezeichnung als Text zum Zeitpunkt
            $table->foreignUuid('result_term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->unsignedInteger('result_revision')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('proposal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->string('type', 20);                                 // eingereicht, rueckfrage, antwort, angenommen, abgelehnt, zurueckgezogen
            $table->uuid('actor_id')->nullable();
            $table->string('actor_label', 120)->nullable();
            $table->boolean('by_author')->default(false);               // Ereignis der einreichenden Person (Löschfrist)
            $table->text('message')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_events');
        Schema::dropIfExists('proposals');
    }
};
