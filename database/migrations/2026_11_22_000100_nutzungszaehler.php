<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistik ohne Cookies (#495, P6 aus DEC-039): Tageszähler je Kanal, Art und Gegenstand – eine Zeile je
 * Kombination, nie je Aufruf, ohne IP-Adresse, Browser-Kennung oder sonstigen Personenbezug.
 *
 * abwaertskompatibel: nur eine neue Tabelle; die Vorversion kennt sie nicht und läuft unverändert weiter (U18).
 */
return new class extends Migration
{
    public function up(): void
    {
        // abwaertskompatibel: neue Tabelle, nichts umbenannt oder entfernt
        Schema::create('usage_counts', function (Blueprint $t) {
            $t->id();
            $t->date('day');
            $t->string('channel', 12);
            $t->string('metric', 20);
            $t->string('subject', 120)->default('');
            $t->unsignedInteger('count')->default(0);
            $t->unique(['day', 'channel', 'metric', 'subject']);
            $t->index(['channel', 'metric', 'day']);
        });
        StrictSchema::harden('usage_counts', [
            'usage_counts_channel_check' => "\"channel\" in ('web', 'extension', 'embed', 'api', 'mcp')",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counts');
    }
};
