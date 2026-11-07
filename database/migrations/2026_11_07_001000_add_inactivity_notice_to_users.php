<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Löschfrist inaktiver Community-Konten (#42): Hinweis 30 Tage vor der Löschung nach 24 Monaten. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->timestamp('inactivity_notice_sent_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('inactivity_notice_sent_at'));
    }
};
