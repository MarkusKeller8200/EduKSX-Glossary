<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verarbeitete Ereignisse von Ident für die Idempotenz (#187, Plattform S4). Aus eduksx/core
 * (php artisan vendor:publish --tag=eduksx-events-migrations).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('received_events')) {
            return;
        }
        Schema::create('received_events', function (Blueprint $t) {
            $t->uuid('event_id')->primary();
            $t->string('type', 120);
            $t->string('source', 255);
            $t->timestamp('received_at')->index();
        });
        StrictSchema::harden('received_events', [
            'received_events_type_check' => 'length("type") between 5 and 120',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('received_events');
    }
};
