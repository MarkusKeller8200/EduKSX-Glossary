<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Als Moderator/in bewerben (#460): Anfragen mit Begründung und Entscheid, dazu der Zeitpunkt, an dem der einmalige
 * Hinweis nach der Anmeldung gezeigt wurde.
 *
 * abwaertskompatibel: neue Tabelle und eine neue, leere Spalte; die Vorversion läuft unverändert weiter (U18).
 */
return new class extends Migration
{
    public function up(): void
    {
        // abwaertskompatibel: neue Tabelle und nullable Spalte, nichts umbenannt oder entfernt
        Schema::create('role_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('user_id')->index()->constrained('users')->cascadeOnDelete();
            $t->string('role', 30);
            $t->text('reason');
            $t->string('status', 10)->default('open')->index();
            $t->foreignUuid('decided_by_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->text('decision_note')->nullable();
            $t->timestamps();
        });
        StrictSchema::harden('role_requests', [
            'role_requests_status_check' => "\"status\" in ('open', 'accepted', 'rejected')",
            'role_requests_role_check' => "\"role\" in ('moderator')",
        ]);
        StrictSchema::addColumns('users', function (Blueprint $t) {
            $t->timestamp('moderator_notice_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('moderator_notice_seen_at'));
        Schema::dropIfExists('role_requests');
    }
};
