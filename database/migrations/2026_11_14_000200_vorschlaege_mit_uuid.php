<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Vorschläge über UUID (#322, API-Standard A1, DB-Standard A3), Schritt 1 nach U18: Spalte `uuid` (UUID v7) ergänzen
 * und für bestehende Vorschläge in der Reihenfolge ihrer Entstehung befüllen; die API nutzt nur noch sie.
 * Schritt 2 (eine Version später): `uuid` wird Primärschlüssel `id`, auch in proposal_events.
 */
return new class extends Migration
{
    public function up(): void
    {
        StrictSchema::addColumns('proposals', function (Blueprint $t) {
            $t->uuid('uuid')->nullable();
            $t->unique('uuid');
        });
        DB::table('proposals')->whereNull('uuid')->orderBy('id')->lazyById()->each(function (object $row): void {
            DB::table('proposals')->where('id', $row->id)->update(['uuid' => (string) Str::uuid7()]);
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $t) {
            $t->dropUnique(['uuid']);
            $t->dropColumn('uuid');
        });
    }
};
