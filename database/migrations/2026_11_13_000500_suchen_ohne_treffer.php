<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suchen ohne Treffer (X4, #285): normalisierter Suchtext mit Zähler, erstes und letztes Auftreten. Ohne Person und
 * ohne IP-Adresse; gelöscht 90 Tage nach dem letzten Auftreten (glossary:loeschfristen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_misses', function (Blueprint $t) {
            $t->id();
            $t->string('query', 100)->unique();
            $t->unsignedInteger('count')->default(1);
            $t->timestamp('first_seen_at');
            $t->timestamp('last_seen_at')->index();
        });
        StrictSchema::harden('search_misses', [
            'search_misses_query_check' => 'length("query") between 2 and 100',
            'search_misses_count_check' => '"count" >= 1',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('search_misses');
    }
};
