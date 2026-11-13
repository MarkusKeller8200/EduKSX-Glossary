<?php

use App\Database\StrictSchema;
use App\Enums\RelationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beziehungen zwischen Begriffen (X2, #283, KONZEPT-DATENBANK): Nachfolger, siehe auch, Ober- und Unterbegriff.
 * Gespeichert in eine Richtung, je Paar und Art einmal. Teil der Revision des Begriffs, von dem sie ausgehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_relations', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('related_term_id')->index()->constrained('terms')->cascadeOnDelete();
            $t->string('relation_type', 20);
            $t->timestamps();
            $t->unique(['term_id', 'related_term_id', 'relation_type']);
        });
        StrictSchema::harden('term_relations', [
            'term_relations_relation_type_check' => RelationType::check('relation_type'),
            'term_relations_not_self_check' => '"term_id" <> "related_term_id"',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('term_relations');
    }
};
