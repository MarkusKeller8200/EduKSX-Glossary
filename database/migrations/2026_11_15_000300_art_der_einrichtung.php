<?php

use App\Enums\InstitutionKind;
use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Art der Einrichtung neu (#415): `users.institution_kind` mit der erweiterten Auswahl und dem Merkmal
 * `is_unified_municipality` (Einheitsgemeinde, nur bei Schulen). `institution_type` bleibt nach U18 eine Version lang
 * stehen (die vorherige Version liest sie noch) und entfällt danach; Werte gibt es noch keine (Entscheid 15.11.2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        StrictSchema::addColumns('users', function (Blueprint $t) {
            $t->string('institution_kind', 30)->nullable();
            $t->boolean('is_unified_municipality')->default(false);
        }, [
            'institution_kind' => ['users_institution_kind_check', InstitutionKind::check('institution_kind', true)],
        ]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['institution_kind', 'is_unified_municipality']));
    }
};
