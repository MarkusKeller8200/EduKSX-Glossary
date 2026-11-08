<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einwilligung in News (#169, keine Werbung): freiwillig, Vorgabe nein. `news_answered_at` = Zeitpunkt der letzten
 * Antwort (NULL = noch nie gefragt; dann einmal nach der Anmeldung fragen). Wird mit dem Konto gelöscht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('news_opt_in')->default(false);
            $table->timestamp('news_answered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['news_opt_in', 'news_answered_at']);
        });
    }
};
