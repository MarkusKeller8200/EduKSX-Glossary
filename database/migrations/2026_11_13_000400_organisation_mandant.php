<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organisation als Mandant (X3, #284): optionaler Microsoft-Tenant (Entra ID) je Organisation, eindeutig. Grundlage
 * für die Zuordnung bei der Microsoft-Anmeldung; verleiht keine Rechte.
 */
return new class extends Migration
{
    public function up(): void
    {
        StrictSchema::addColumns('organizations', function (Blueprint $t) {
            $t->string('entra_tenant_id', 36)->nullable();
            $t->unique('entra_tenant_id');
        }, ['entra_tenant_id' => ['organizations_entra_tenant_id_check', '"entra_tenant_id" is null or length("entra_tenant_id") = 36']]);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $t) {
            $t->dropUnique(['entra_tenant_id']);
            $t->dropColumn('entra_tenant_id');
        });
    }
};
