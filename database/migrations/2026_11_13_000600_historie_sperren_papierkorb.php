<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Änderungshistorie, optimistisches Sperren und Papierkorb (A6, A7, B3 DB-Standard, #286).
 * - data_changes: Historie aller Tabellen ohne eigenes Revisionssystem; Werte nach Datenklasse (#276).
 * - lock_version: änderbare Fachtabellen (Begriffe, Kategorien, Herkünfte, Organisationen).
 * - deleted_at/deleted_by_id: Papierkorb für Hauptobjekte (Kategorien, Herkünfte, Organisationen).
 */
return new class extends Migration
{
    private const ACTIONS = "'created', 'updated', 'deleted', 'restored'";

    private const SOURCES = "'web', 'api', 'import', 'mcp', 'job', 'system', 'migration'";

    public function up(): void
    {
        Schema::create('data_changes', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_id')->unique();
            $t->string('subject_type', 60);
            $t->string('subject_id', 64);
            $t->uuid('organization_id')->nullable()->index();
            $t->string('action', 20);
            $t->json('changes')->nullable();
            $t->string('actor_type', 20);
            $t->uuid('actor_id')->nullable()->index();
            $t->string('actor_label', 120)->nullable();
            $t->string('source', 20);
            $t->uuid('transaction_id')->index();
            $t->string('request_id', 100)->nullable();
            $t->string('schema_version', 20);
            $t->timestamp('occurred_at')->index();
            $t->index(['subject_type', 'subject_id']);
        });
        StrictSchema::harden('data_changes', [
            'data_changes_action_check' => '"action" in ('.self::ACTIONS.')',
            'data_changes_source_check' => '"source" in ('.self::SOURCES.')',
        ]);

        foreach (['terms', 'categories', 'sources', 'organizations'] as $table) {
            StrictSchema::addColumns($table, function (Blueprint $t) {
                $t->unsignedInteger('lock_version')->default(0);
            }, ['lock_version' => ["{$table}_lock_version_check", '"lock_version" >= 0']]);
        }
        foreach (['categories', 'sources', 'organizations'] as $table) {
            StrictSchema::addColumns($table, function (Blueprint $t) use ($table) {
                $t->timestamp('deleted_at')->nullable();
                $t->uuid('deleted_by_id')->nullable();
                $t->index('deleted_by_id', "{$table}_deleted_by_id_index");
            }, [], ['deleted_by_id' => ['users', 'set null']]);
        }
    }

    public function down(): void
    {
        foreach (['categories', 'sources', 'organizations'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex("{$t->getTable()}_deleted_by_id_index");
                $t->dropColumn(['deleted_by_id', 'deleted_at']);
            });
        }
        foreach (['terms', 'categories', 'sources', 'organizations'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('lock_version'));
        }
        Schema::dropIfExists('data_changes');
    }
};
