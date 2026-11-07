<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kontoangaben (#39, docs/KONZEPT-BENUTZER.md 4): Vor- und Nachname, freiwillig Community-Name, Funktion,
 * Einrichtungsart und Gemeinde/Schule; letzte Anmeldung nur als Datum. «name» bleibt als Anzeigename (Vor- und
 * Nachname) erhalten und wird vom Model nachgeführt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 80)->default('');
            $table->string('last_name', 80)->default('');
            $table->string('community_name', 40)->nullable()->unique();
            $table->string('function', 100)->nullable();
            $table->string('institution_type', 20)->nullable();     // schule, schulgemeinde, gemeinde, kanton, andere
            $table->string('institution_name', 150)->nullable();
            $table->date('last_login_on')->nullable();
        });

        // Bisherigen Namen aufteilen: letztes Wort = Nachname
        foreach (DB::table('users')->select(['id', 'name'])->get() as $user) {
            $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
            $last = count($parts) > 1 ? (string) array_pop($parts) : '';
            DB::table('users')->where('id', $user->id)->update([
                'first_name' => mb_substr(implode(' ', $parts), 0, 80),
                'last_name' => mb_substr($last, 0, 80),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['community_name']);
            $table->dropColumn(['first_name', 'last_name', 'community_name', 'function', 'institution_type', 'institution_name', 'last_login_on']);
        });
    }
};
