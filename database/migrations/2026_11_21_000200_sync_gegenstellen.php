<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entwicklungs-Sync (#478, DEC-041 A20): Gegenstellen mit Adresse, verschlüsseltem Token und den Prüfsummen beim
 * letzten Holen bzw. Hochladen (Grundlage der Konfliktprüfung A17).
 *
 * abwaertskompatibel: nur eine neue Tabelle; die Vorversion kennt sie nicht und läuft unverändert weiter (U18).
 */
return new class extends Migration
{
    public function up(): void
    {
        // abwaertskompatibel: neue Tabelle, nichts umbenannt oder entfernt
        Schema::create('sync_peers', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name', 60);
            $t->string('url', 255);
            $t->text('token');
            $t->string('remote_role', 20)->nullable();
            $t->json('base')->nullable();
            $t->timestamp('last_pulled_at')->nullable();
            $t->timestamp('last_pushed_at')->nullable();
            $t->text('last_result')->nullable();
            $t->foreignUuid('created_by_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        StrictSchema::harden('sync_peers', [
            'sync_peers_remote_role_check' => "\"remote_role\" is null or \"remote_role\" in ('master', 'test', 'development', 'instance')",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_peers');
    }
};
