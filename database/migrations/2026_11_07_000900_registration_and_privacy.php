<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selbstregistrierung und Passwort vergessen (#41): Kenntnisnahme der Datenschutzerklärung (Version, Zeitpunkt;
 * keine pauschale Einwilligung, DEC-019) und Tokens für das Zurücksetzen des Passworts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('privacy_notice_version', 20)->nullable();
            $table->timestamp('privacy_notice_acknowledged_at')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['privacy_notice_version', 'privacy_notice_acknowledged_at']));
    }
};
