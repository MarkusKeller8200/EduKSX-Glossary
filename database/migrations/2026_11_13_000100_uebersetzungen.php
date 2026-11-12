<?php

use App\Database\StrictSchema;
use App\Enums\Locale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Übersetzungen (X1, #282, KONZEPT-DATENBANK): Begriffe, Kategorien und Herkünfte auf fr, it, en; Deutsch steht
 * in der Haupttabelle. Je Objekt und Sprache eine Zeile. Begriffs-Übersetzungen sind Teil der Revision.
 */
return new class extends Migration
{
    public function up(): void
    {
        $locales = "\"locale\" in ('".implode("', '", Locale::translated())."')";

        Schema::create('term_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $t->string('locale', 10);
            $t->string('abbreviation', 60)->nullable();
            $t->string('name')->nullable();
            $t->string('short_description', 160)->nullable();
            $t->text('description')->nullable();
            $t->timestamps();
            $t->unique(['term_id', 'locale']);
        });
        StrictSchema::harden('term_translations', ['term_translations_locale_check' => $locales]);

        foreach (['category' => 'categories', 'source' => 'sources'] as $item => $parent) {
            Schema::create("{$item}_translations", function (Blueprint $t) use ($item, $parent) {
                $t->id();
                $t->foreignUuid("{$item}_id")->constrained($parent)->cascadeOnDelete();
                $t->string('locale', 10);
                $t->string('name')->nullable();
                $t->string('label', 60)->nullable();
                $t->text('description')->nullable();
                $t->timestamps();
                $t->unique(["{$item}_id", 'locale']);
            });
            StrictSchema::harden("{$item}_translations", ["{$item}_translations_locale_check" => $locales]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('source_translations');
        Schema::dropIfExists('category_translations');
        Schema::dropIfExists('term_translations');
    }
};
