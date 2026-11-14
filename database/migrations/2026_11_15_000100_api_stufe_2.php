<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API-Standard Stufe 2 (#347, #348):
 * - proposals.lock_version: starker Objekt-ETag und If-Match beim Zurückziehen über die API (B2)
 * - api_idempotency_keys: gleiche Anfrage mit gleichem Idempotency-Key liefert dieselbe Antwort (B3), 24 Stunden
 *
 * abwärtskompatibel: nur eine neue Spalte mit Vorgabe und eine neue Tabelle; die Vorversion läuft unverändert weiter.
 */
return new class extends Migration
{
    public function up(): void
    {
        // abwärtskompatibel: neue Spalte mit Vorgabe 0 und neue Tabelle, nichts umbenannt oder entfernt (U18)
        StrictSchema::addColumns('proposals', function (Blueprint $t) {
            $t->unsignedInteger('lock_version')->default(0);
        }, ['lock_version' => ['proposals_lock_version_check', '"lock_version" >= 0']]);

        Schema::create('api_idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('service_account_id')->constrained('service_accounts')->cascadeOnDelete();
            $t->string('idempotency_key', 200);
            $t->string('request_hash', 64);
            $t->unsignedSmallInteger('response_status');
            $t->text('response_body');
            $t->timestamp('created_at')->index();
            $t->unique(['service_account_id', 'idempotency_key']);
        });
        StrictSchema::harden('api_idempotency_keys', [
            'api_idempotency_keys_status_check' => '"response_status" between 100 and 599',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_keys');
        Schema::table('proposals', fn (Blueprint $t) => $t->dropColumn('lock_version'));
    }
};
