<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Externe Anmeldedienste (#45, docs/KONZEPT-BENUTZER.md 6): Identität = Anbieter + bestätigter Aussteller + stabile
 * Kennung (nie die E-Mail). Für Microsoft zusätzlich Tenant- und Objekt-ID. Keine weiteren Profildaten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 30);                 // microsoft, später edulog
            $table->string('issuer', 255);
            $table->string('subject', 255);
            $table->string('tenant_id', 64)->nullable();
            $table->string('object_id', 64)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'issuer', 'subject']);
        });

        // Konten ohne eigenes Passwort (nur Anmeldedienst) erkennen
        Schema::table('users', fn (Blueprint $table) => $table->boolean('password_set')->default(true));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('password_set'));
        Schema::dropIfExists('user_identities');
    }
};
