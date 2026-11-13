<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Externe Identitäten (W8, #277, DB-Standard): eindeutig über issuer + subject, unabhängig vom Anbieter. Der
 * Aussteller ist bei Microsoft je Tenant eine eigene URL; tenant_id bleibt als abgeleitete Angabe. Zeilen bleiben
 * unverändert (Zählung vorher/nachher); bei Doppelten bricht die Migration ab.
 */
return new class extends Migration
{
    public function up(): void
    {
        $before = DB::table('user_identities')->count();
        $duplicates = DB::table('user_identities')->select('issuer', 'subject')->groupBy('issuer', 'subject')
            ->havingRaw('count(*) > 1')->count();
        if ($duplicates > 0) {
            throw new RuntimeException("user_identities: {$duplicates} doppelte Paare issuer + subject – zuerst bereinigen.");
        }

        Schema::table('user_identities', function (Blueprint $t) {
            $t->dropUnique(['provider', 'issuer', 'subject']);
            $t->unique(['issuer', 'subject']);
        });

        if (DB::table('user_identities')->count() !== $before) {
            throw new RuntimeException('user_identities: Anzahl Zeilen hat sich verändert.');
        }
    }

    public function down(): void
    {
        Schema::table('user_identities', function (Blueprint $t) {
            $t->dropUnique(['issuer', 'subject']);
            $t->unique(['provider', 'issuer', 'subject']);
        });
    }
};
