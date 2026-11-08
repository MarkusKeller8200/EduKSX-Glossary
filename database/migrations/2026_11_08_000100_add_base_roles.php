<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rollen Gast, Benutzer und Organisations-Admin mit den neuen Rechten export, personal-display und service-tokens
 * (#110, DEC-017). «propose» war bisher für jedes Konto fest gesetzt und kommt jetzt aus der Rolle: Konten ohne
 * Rolle (bisher «Community») werden Benutzer. Moderator und Admin erhalten die neuen Grundrechte, Admin zusätzlich
 * die Servicekonten.
 */
return new class extends Migration
{
    private const ROLES = [
        ['gast', 'Gast', 'Angemeldet, nur lesen', 1, []],
        ['benutzer', 'Benutzer', 'Exportieren, Vorschläge einreichen, persönliche Darstellung', 2,
            ['propose', 'export', 'personal-display']],
        ['org-admin', 'Organisations-Admin', 'Wie Benutzer, dazu Servicekonten mit Token', 5,
            ['propose', 'export', 'personal-display', 'service-tokens']],
    ];

    /** Zusätzliche Rechte bestehender Rollen. */
    private const EXTRA = [
        'moderator' => ['export', 'personal-display'],
        'admin' => ['export', 'personal-display', 'service-tokens'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::ROLES as [$key, $name, $description, $order, $permissions]) {
            if (DB::table('roles')->where('key', $key)->exists()) {
                continue;
            }
            $id = (string) Str::uuid7();
            DB::table('roles')->insert([
                'id' => $id, 'key' => $key, 'name' => $name, 'description' => $description,
                'is_system' => false, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($permissions as $permission) {
                DB::table('role_permissions')->insert(['role_id' => $id, 'permission' => $permission]);
            }
        }

        foreach (self::EXTRA as $key => $permissions) {
            $roleId = DB::table('roles')->where('key', $key)->value('id');
            if ($roleId === null) {
                continue;
            }
            foreach ($permissions as $permission) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission' => $permission]);
            }
        }

        // Bisherige Community-Konten (ohne Rolle) → Benutzer, wie bisher mit Vorschlagsrecht (bestätigt 08.11.2026)
        $userRole = (string) DB::table('roles')->where('key', 'benutzer')->value('id');
        $without = DB::table('users')->whereNotIn('id', DB::table('user_roles')->select('user_id'))->pluck('id');
        foreach ($without as $userId) {
            DB::table('user_roles')->insert(['user_id' => $userId, 'role_id' => $userRole]);
            DB::table('user_audit')->insert([
                'actor_label' => 'Migration', 'target_user_id' => $userId, 'target_role_id' => $userRole,
                'action' => 'role.assigned', 'new' => json_encode(['role' => 'benutzer']), 'result' => 'ok',
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('key', array_column(self::ROLES, 0))->delete();
        DB::table('role_permissions')->whereIn('permission', ['export', 'personal-display', 'service-tokens'])->delete();
    }
};
