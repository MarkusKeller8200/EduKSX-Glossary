<?php

use App\Access\Service\ServiceAccountManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Scopes nach API-Standard (#323): vorhandene Servicekonten erhalten zu den alten Namen (propose, read-org) die neuen
 * (glossary:proposals.create, glossary:proposals.read, glossary:terms.read). Die alten bleiben nach U18 gespeichert,
 * damit die vorherige Version beim Update weiterläuft; eine Version später entfernen.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_accounts')->orderBy('id')->lazyById()->each(function (object $row): void {
            $scopes = json_decode((string) $row->scopes, true);
            if (! is_array($scopes)) {
                return;
            }
            $expanded = ServiceAccountManager::expand(array_values(array_map('strval', $scopes)));
            if ($expanded !== $scopes) {
                DB::table('service_accounts')->where('id', $row->id)->update(['scopes' => json_encode($expanded)]);
            }
        });
    }

    public function down(): void
    {
        // Alte Namen sind noch gespeichert; die neuen stören die vorherige Version nicht.
    }
};
