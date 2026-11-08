<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servicekonten mit Token (#47, docs/KONZEPT-BENUTZER.md 2.2, DEC-017): getrennt von Personenkonten, kein Login,
 * Token nur als SHA-256-Hash, befristet, widerrufbar, mit Scopes; eigenes Protokoll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->string('purpose', 255);
            $table->foreignUuid('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active');        // active, disabled
            $table->json('scopes');
            $table->timestamps();
        });

        Schema::create('service_account_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_account_id')->constrained('service_accounts')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('token_hash', 64)->unique();
            $table->string('prefix', 12);                            // Anfang zum Wiedererkennen, kein Geheimnis
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_account_audit', function (Blueprint $table) {
            $table->id();
            $table->uuid('service_account_id')->nullable()->index(); // kein Fremdschlüssel: Protokoll bleibt
            $table->uuid('token_id')->nullable();
            $table->uuid('actor_id')->nullable()->index();
            $table->string('actor_label', 120)->nullable();
            $table->string('action', 60);
            $table->json('details')->nullable();
            $table->string('result', 20)->default('ok');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_account_audit');
        Schema::dropIfExists('service_account_tokens');
        Schema::dropIfExists('service_accounts');
    }
};
