<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail-Protokoll (#463): versendete und fehlgeschlagene Mails ohne Inhalt – Zeitpunkt, Art, maskierte Adresse,
 * Ergebnis. 90 Tage aufbewahrt (glossary:loeschfristen).
 *
 * abwaertskompatibel: nur eine neue Tabelle; die Vorversion läuft unverändert weiter (U18).
 */
return new class extends Migration
{
    public function up(): void
    {
        // abwaertskompatibel: neue Tabelle, nichts umbenannt oder entfernt
        Schema::create('mail_log', function (Blueprint $t) {
            $t->id();
            $t->timestamp('created_at')->index();
            $t->string('kind', 80);
            $t->string('recipient', 255);
            $t->string('status', 10);
            $t->string('mailer', 20);
            $t->string('error', 120)->nullable();
        });
        StrictSchema::harden('mail_log', [
            'mail_log_status_check' => "\"status\" in ('sent', 'failed')",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_log');
    }
};
