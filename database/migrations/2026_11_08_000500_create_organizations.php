<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organisationen (#148, DEC-025, docs/KONZEPT-ORGANISATION.md).
 *
 * - `organizations`: Name; gegründet von einem Konto (wird bei dessen Löschung geleert).
 * - `organization_members`: höchstens eine Organisation je Konto (unique user_id); Rolle admin/member, Status
 *   active/blocked; `granted_role` merkt sich, ob die globale Rolle «Organisations-Admin» durch die Organisation
 *   vergeben wurde (nur dann wird sie beim Entzug wieder entfernt).
 * - `organization_invitations`: Einladung je E-Mail-Adresse, 14 Tage gültig, danach gelöscht.
 * - `organization_term_overrides`: Darstellung eines Begriffs für alle Mitglieder (wie die persönliche Darstellung).
 * - `service_accounts.organization_id`: Servicekonten einer Organisation (Scope «read-org»).
 * - `proposals.organization_id`: Vorschläge von Mitgliedern; Status «org_pruefung», bis ein Organisations-Admin
 *   bestätigt (dann Moderation) oder ablehnt.
 * - Einladungen werden nicht automatisch angenommen: die eingeladene Person bestätigt aktiv.
 * - Recht «create-organization» (Grundrecht) für Benutzer, Organisations-Admin, Moderator und Admin.
 */
return new class extends Migration
{
    private const ROLES_WITH_CREATE = ['benutzer', 'org-admin', 'moderator', 'admin'];

    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');      // admin, member
            $table->string('status', 20)->default('active');    // active, blocked
            $table->boolean('granted_role')->default(false);
            $table->timestamps();
            $table->index(['organization_id', 'role']);
        });

        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->foreignUuid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['organization_id', 'email']);
            $table->index('email');
        });

        Schema::create('organization_term_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $table->string('abbreviation_override', 60)->nullable();
            $table->string('name_override')->nullable();
            $table->string('short_description_override', 160)->nullable();
            $table->text('description_override')->nullable();
            $table->json('aliases_override')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'term_id']);
        });

        Schema::table('service_accounts', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->nullable()->constrained()->nullOnDelete();
        });

        foreach (self::ROLES_WITH_CREATE as $key) {
            $roleId = DB::table('roles')->where('key', $key)->value('id');
            if ($roleId !== null) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission' => 'create-organization']);
            }
        }
        DB::table('roles')->where('key', 'org-admin')->update([
            'description' => 'Wie Benutzer, dazu Verwaltung der eigenen Organisation und Servicekonten mit Token',
        ]);
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'create-organization')->delete();
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
        Schema::table('service_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
        Schema::dropIfExists('organization_term_overrides');
        Schema::dropIfExists('organization_invitations');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }
};
