<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Rollen und Rechte (#36, docs/KONZEPT-BENUTZER.md, DEC-017): Rollen mit einstellbaren Rechten, Zuordnung zu Konten,
 * Audit-Protokoll. Der Schalter users.is_admin geht in der Rolle Admin auf – nie automatisch in Developer.
 */
return new class extends Migration
{
    /** Voreinstellung der Rollen (Developer hat alle Rechte, ohne Einträge in role_permissions). */
    private const ROLES = [
        ['moderator', 'Moderator', 'Vorschläge prüfen, Rückfragen, Entwürfe bearbeiten', false, 10, ['propose', 'moderate', 'edit-terms']],
        ['admin', 'Admin', 'Veröffentlichen, Benutzer und Rollen, Import und Listen', false, 20,
            ['propose', 'moderate', 'edit-terms', 'publish', 'edit-official', 'manage-users', 'import', 'lists']],
        ['developer', 'Developer', 'Alle Rechte sowie System und Sicherheit (geschützte Systemrolle)', true, 30, []],
    ];

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 40)->unique();
            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('permission', 40);
            $table->primary(['role_id', 'permission']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
            $table->index('role_id');
        });

        Schema::create('user_audit', function (Blueprint $table) {
            $table->id();
            $table->uuid('actor_id')->nullable()->index();          // kein Fremdschlüssel: Protokoll bleibt
            $table->string('actor_label', 120)->nullable();         // z. B. «Serverbefehl», «Migration»
            $table->uuid('target_user_id')->nullable()->index();
            $table->uuid('target_role_id')->nullable()->index();
            $table->string('action', 60);
            $table->json('old')->nullable();
            $table->json('new')->nullable();
            $table->string('result', 20)->default('ok');            // ok, denied
            $table->timestamp('created_at')->useCurrent();
        });

        $now = now();
        $ids = [];
        foreach (self::ROLES as [$key, $name, $description, $system, $order, $permissions]) {
            $ids[$key] = (string) Str::uuid7();
            DB::table('roles')->insert([
                'id' => $ids[$key], 'key' => $key, 'name' => $name, 'description' => $description,
                'is_system' => $system, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($permissions as $permission) {
                DB::table('role_permissions')->insert(['role_id' => $ids[$key], 'permission' => $permission]);
            }
        }

        // Bisherige Admins → Rolle Admin (Developer nur ausdrücklich über glossary:developer)
        $adminId = (string) DB::table('roles')->where('key', 'admin')->value('id');
        foreach (DB::table('users')->where('is_admin', true)->pluck('id') as $userId) {
            DB::table('user_roles')->insert(['user_id' => $userId, 'role_id' => $adminId]);
            DB::table('user_audit')->insert([
                'actor_label' => 'Migration', 'target_user_id' => $userId, 'target_role_id' => $adminId,
                'action' => 'role.assigned', 'new' => json_encode(['role' => 'admin']), 'result' => 'ok', 'created_at' => $now,
            ]);
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        $admin = DB::table('roles')->whereIn('key', ['admin', 'developer'])->pluck('id');
        DB::table('users')->whereIn('id', DB::table('user_roles')->whereIn('role_id', $admin)->pluck('user_id'))
            ->update(['is_admin' => true]);
        Schema::dropIfExists('user_audit');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
