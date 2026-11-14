<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plattform-IDs (#311, DB-Standard E3): `platform_id` in organizations und users – leer, bis das Glossary an
 * EduKSX-Ident angeschlossen ist (Organisationen später mit der ID aus Ident, #186). Eindeutig, mit Index.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['organizations', 'users'] as $table) {
            StrictSchema::addColumns($table, function (Blueprint $t) {
                $t->uuid('platform_id')->nullable();
                $t->unique('platform_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['organizations', 'users'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique(['platform_id']);
                $t->dropColumn('platform_id');
            });
        }
    }
};
