<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ereignis-IDs (#332, DB-Standard A3/E4): Änderungs-Feed, Verlauf der Vorschläge und Sicherheitsprotokolle erhalten
 * zusätzlich eine UUID v7 `event_id`; bestehende Zeilen in ihrer Reihenfolge. Die fortlaufende Zahl bleibt (Cursor des
 * Feeds), Feed und Abgleich ändern sich nicht. `data_changes` hat sie schon.
 */
return new class extends Migration
{
    private const TABLES = ['term_publication_events', 'proposal_events', 'user_audit', 'service_account_audit'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            StrictSchema::addColumns($table, function (Blueprint $t) {
                $t->uuid('event_id')->nullable();
                $t->unique('event_id');
            });
            DB::table($table)->whereNull('event_id')->orderBy('id')->lazyById()->each(function (object $row) use ($table): void {
                DB::table($table)->where('id', $row->id)->update(['event_id' => (string) Str::uuid7()]);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['event_id']);
                $t->dropColumn('event_id');
            });
        }
    }
};
