<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einschränkung der Bearbeitung (#312, DB-Standard B13): Merkmal je Konto – die Daten bleiben gespeichert, werden aber
 * nicht mehr angezeigt, weitergegeben oder geändert (z. B. während eine Berichtigung strittig ist).
 */
return new class extends Migration
{
    public function up(): void
    {
        StrictSchema::addColumns('users', function (Blueprint $t) {
            $t->boolean('is_processing_restricted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_processing_restricted'));
    }
};
