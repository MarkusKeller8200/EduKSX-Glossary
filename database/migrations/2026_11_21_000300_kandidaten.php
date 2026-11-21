<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kandidaten (DEC-039 Abschnitt 5, DEC-042, #480; erweitert mit #435): gemeldete Wörter aus Erweiterung und
 * Einbindung – nur Wort, Zähler, Adresse ohne Abfrage und optional Kommentare, nie Seitentext oder IP-Adresse.
 *
 * abwaertskompatibel: nur eine neue Tabelle; die Vorversion kennt sie nicht und läuft unverändert weiter (U18).
 */
return new class extends Migration
{
    public function up(): void
    {
        // abwaertskompatibel: neue Tabelle, nichts umbenannt oder entfernt
        Schema::create('term_candidates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('word', 80);
            $t->string('normalized', 80)->index();
            $t->string('kind', 10);
            $t->string('channel', 20);
            $t->foreignUuid('term_id')->nullable()->index()->constrained('terms')->nullOnDelete();
            $t->unsignedInteger('count')->default(1);
            $t->string('first_url', 500)->nullable();
            $t->string('last_url', 500)->nullable();
            $t->json('comments')->nullable();
            $t->string('status', 10)->default('new')->index();
            $t->text('reason')->nullable();
            $t->foreignUuid('result_term_id')->nullable()->index()->constrained('terms')->nullOnDelete();
            $t->foreignUuid('reported_by_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->foreignUuid('decided_by_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });
        StrictSchema::harden('term_candidates', [
            'term_candidates_kind_check' => "\"kind\" in ('missing', 'wrong')",
            'term_candidates_channel_check' => "\"channel\" in ('extension', 'embed', 'text', 'search')",
            'term_candidates_status_check' => "\"status\" in ('new', 'accepted', 'alias', 'rejected')",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('term_candidates');
    }
};
