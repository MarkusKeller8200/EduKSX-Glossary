<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quellenprüfung (#69): Belegstatus je Herkunft eines Begriffs. Das Prüfdatum ist das bestehende `retrieved_at`.
 * Redaktionelle Angaben – nicht Teil der Revisionen und nicht öffentlich.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('term_sources', function (Blueprint $table) {
            $table->string('evidence', 20)->nullable();   // amtlich, belegt, sekundaer, offen
        });
    }

    public function down(): void
    {
        Schema::table('term_sources', function (Blueprint $table) {
            $table->dropColumn('evidence');
        });
    }
};
