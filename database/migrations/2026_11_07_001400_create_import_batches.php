<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import-Protokoll (#64, docs/KONZEPT-DATENMODELL.md): wer wann welche Datei mit welchem Ergebnis importiert hat.
 * Ohne Zeileninhalte (die Änderungen stehen im Journal der Revisionen); 12 Monate aufbewahrt (glossary:loeschfristen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_label', 120)->nullable();
            $table->string('file_name', 255);
            $table->unsignedTinyInteger('format_version');
            $table->unsignedInteger('rows');
            $table->unsignedInteger('created_count');
            $table->unsignedInteger('updated_count');
            $table->unsignedInteger('error_count');
            $table->unsignedInteger('legacy_count');
            $table->json('errors')->nullable();   // Zeile und Meldung, höchstens 100
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
