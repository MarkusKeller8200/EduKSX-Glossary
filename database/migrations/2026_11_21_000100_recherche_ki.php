<?php

use App\Enums\ResearchBasis;
use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recherche eines Begriffs (#479): `research_basis` (KI-unterstützt bzw. von Mensch geprüft), dazu `reviewed_at` und
 * `reviewed_by_id` der letzten Prüfung durch einen Menschen (auch für das Prüfintervall, #436). Redaktionelle Merkmale
 * am Begriff, nicht im Abbild der Revision (wie die Prüfung der Herkunft).
 *
 * abwaertskompatibel: nur neue, leere Spalten; die Vorversion liest sie nicht und läuft unverändert weiter (U18).
 */
return new class extends Migration
{
    public function up(): void
    {
        // abwaertskompatibel: neue nullable Spalten, nichts umbenannt oder entfernt
        StrictSchema::addColumns('terms', function (Blueprint $t) {
            $t->string('research_basis', 20)->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->uuid('reviewed_by_id')->nullable()->index();
        }, [
            'research_basis' => ['terms_research_basis_check', ResearchBasis::check('research_basis', true)],
        ], [
            'reviewed_by_id' => ['users', 'set null'],
        ]);
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $t) {
            $t->dropIndex(['reviewed_by_id']);
            $t->dropColumn(['research_basis', 'reviewed_at', 'reviewed_by_id']);
        });
    }
};
