<?php

use App\Database\StrictSchema;
use App\Database\Uebergang012;
use App\Enums\ActorType;
use App\Enums\AliasType;
use App\Enums\AuditResult;
use App\Enums\ChangeType;
use App\Enums\DuplicateMatchType;
use App\Enums\Evidence;
use App\Enums\InstitutionType;
use App\Enums\Locale;
use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use App\Enums\ProposalEventType;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Enums\PublicationAction;
use App\Enums\ServiceAccountStatus;
use App\Enums\TermStatus;
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Bereinigter Anfangsstand 0.12.0 (DEC-030, DEC-033, #266) – abwaertskompatibel: nie produktiv, DEC-030.
 *
 * Ersetzt die 23 Migrationen bis 0.11.0: Namen nach dem DB-Standard (A1), STRICT-Tabellen mit CHECK-Regeln (A4),
 * Werte englisch (KONZEPT-KONVENTIONEN), Fremdschlüssel nach Regel N5 und ein Index auf jeder Fremdschlüssel-Spalte (A3),
 * `terms.published_revision_id` mit zusammengesetztem Fremdschlüssel. Entfallen: `terms.search_terms` (nur noch Aliase
 * vom Typ `search`), `term_sources.url`, `user_identities.object_id`, `import_batches.legacy_count` und die Altwerte
 * (`term_legacy_values`).
 *
 * Bestehende Datenbanken bis 0.11.0 (dev) überführt {@see Uebergang012} in derselben Transaktion: alte Tabellen
 * umbenennen, neue anlegen, Daten übernehmen und zählen, Entfallendes verschlüsselt archivieren, alte Tabellen löschen.
 */
return new class extends Migration
{
    /** Rollen mit Rechten (Stand 0.11.0; Developer hat alle Rechte ohne Einträge). */
    private const ROLES = [
        ['gast', 'Gast', 'Angemeldet, nur lesen', false, 1, []],
        ['benutzer', 'Benutzer', 'Exportieren, Vorschläge einreichen, persönliche Darstellung', false, 2,
            ['propose', 'export', 'personal-display', 'create-organization']],
        ['org-admin', 'Organisations-Admin', 'Wie Benutzer, dazu Verwaltung der eigenen Organisation und Servicekonten mit Token', false, 5,
            ['propose', 'export', 'personal-display', 'service-tokens', 'create-organization']],
        ['moderator', 'Moderator', 'Vorschläge prüfen, Rückfragen, Entwürfe bearbeiten', false, 10,
            ['propose', 'moderate', 'edit-terms', 'export', 'personal-display', 'create-organization']],
        ['admin', 'Admin', 'Veröffentlichen, Benutzer und Rollen, Import und Listen', false, 20,
            ['propose', 'moderate', 'edit-terms', 'publish', 'edit-official', 'manage-users', 'import', 'lists', 'export',
                'personal-display', 'service-tokens', 'create-organization']],
        ['developer', 'Developer', 'Alle Rechte sowie System und Sicherheit (geschützte Systemrolle)', true, 30, []],
    ];

    public function up(): void
    {
        $uebergang = new Uebergang012;
        if ($uebergang->altbestandVorhanden()) {
            $uebergang->run(fn () => $this->createTables());

            return;
        }
        $this->createTables();
        $this->seedRoles();
    }

    public function down(): void
    {
        foreach (array_reverse(self::tables()) as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Alle Tabellen in Reihenfolge der Abhängigkeiten.
     *
     * @return list<string>
     */
    public static function tables(): array
    {
        return [
            'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
            'roles', 'role_permissions', 'user_roles', 'user_audit', 'user_identities',
            'categories', 'sources', 'terms', 'term_revisions', 'term_aliases', 'term_categories', 'term_sources',
            'publication_releases', 'term_publication_events', 'duplicate_decisions', 'import_batches', 'settings',
            'organizations', 'organization_members', 'organization_invitations', 'organization_term_overrides',
            'term_user_overrides', 'service_accounts', 'service_account_tokens', 'service_account_audit',
            'proposals', 'proposal_events',
        ];
    }

    private function createTables(): void
    {
        $this->framework();
        $this->access();
        $this->glossary();
        $this->organizations();
        $this->services();
    }

    private function framework(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');                                   // Anzeigename (Vorgabe: Vor- und Nachname)
            $t->string('first_name', 80)->default('');
            $t->string('last_name', 80)->default('');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->boolean('has_password')->default(true);           // false = nur über Anmeldedienst (#45)
            $t->rememberToken();
            $t->text('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
            $t->unsignedBigInteger('two_factor_last_step')->nullable();
            $t->string('status', 20)->default(UserStatus::Active->value)->index();
            $t->timestamp('blocked_at')->nullable();
            $t->string('community_name', 40)->nullable()->unique();
            $t->string('function', 100)->nullable();
            $t->string('institution_type', 20)->nullable();
            $t->string('institution_name', 150)->nullable();
            $t->date('last_login_on')->nullable();
            $t->string('privacy_notice_version', 20)->nullable();
            $t->timestamp('privacy_notice_acknowledged_at')->nullable();
            $t->timestamp('inactivity_notice_sent_at')->nullable();
            $t->boolean('has_news_opt_in')->default(false);
            $t->timestamp('news_answered_at')->nullable();
            $t->timestamps();
        });
        StrictSchema::harden('users', [
            'users_status_check' => UserStatus::check('status'),
            'users_institution_type_check' => InstitutionType::check('institution_type', true),
            'users_has_password_check' => '"has_password" in (0, 1)',
            'users_has_news_opt_in_check' => '"has_news_opt_in" in (0, 1)',
        ]);

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
        StrictSchema::harden('password_reset_tokens', []);

        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignUuid('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
        StrictSchema::harden('sessions', []);

        Schema::create('cache', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->mediumText('value');
            $t->integer('expiration')->index();
        });
        StrictSchema::harden('cache', []);

        Schema::create('cache_locks', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('owner');
            $t->integer('expiration')->index();
        });
        StrictSchema::harden('cache_locks', []);

        Schema::create('jobs', function (Blueprint $t) {
            $t->id();
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
        StrictSchema::harden('jobs', []);

        Schema::create('job_batches', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('name');
            $t->integer('total_jobs');
            $t->integer('pending_jobs');
            $t->integer('failed_jobs');
            $t->longText('failed_job_ids');
            $t->mediumText('options')->nullable();
            $t->integer('cancelled_at')->nullable();
            $t->integer('created_at');
            $t->integer('finished_at')->nullable();
        });
        StrictSchema::harden('job_batches', []);

        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });
        StrictSchema::harden('failed_jobs', []);
    }

    private function access(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('key', 40)->unique();
            $t->string('name', 80);
            $t->string('description')->nullable();
            $t->boolean('is_system')->default(false);
            $t->unsignedSmallInteger('sort_order')->default(100);
            $t->timestamps();
        });
        StrictSchema::harden('roles', ['roles_is_system_check' => '"is_system" in (0, 1)']);

        Schema::create('role_permissions', function (Blueprint $t) {
            $t->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $t->string('permission', 40);
            $t->primary(['role_id', 'permission']);
        });
        StrictSchema::harden('role_permissions', []);

        Schema::create('user_roles', function (Blueprint $t) {
            $t->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $t->primary(['user_id', 'role_id']);
            $t->index('role_id');
        });
        StrictSchema::harden('user_roles', []);

        // Sicherheitsprotokoll: Verweise ohne Fremdschlüssel (überleben die Kontolöschung, N5), mit Index
        Schema::create('user_audit', function (Blueprint $t) {
            $t->id();
            $t->uuid('actor_id')->nullable()->index();
            $t->string('actor_label', 120)->nullable();
            $t->uuid('target_user_id')->nullable()->index();
            $t->uuid('target_role_id')->nullable()->index();
            $t->string('action', 60);
            $t->json('old')->nullable();
            $t->json('new')->nullable();
            $t->string('result', 20)->default(AuditResult::Ok->value);
            $t->timestamp('created_at')->useCurrent();
        });
        StrictSchema::harden('user_audit', ['user_audit_result_check' => AuditResult::check('result')]);

        Schema::create('user_identities', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('user_id')->index()->constrained('users')->cascadeOnDelete();
            $t->string('provider', 30);
            $t->string('issuer', 255);
            $t->string('subject', 255);
            $t->string('tenant_id', 64)->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'issuer', 'subject']);
        });
        StrictSchema::harden('user_identities', []);
    }

    private function glossary(): void
    {
        foreach (['categories', 'sources'] as $list) {
            Schema::create($list, function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('key', 50)->unique();
                $t->string('abbreviation', 30)->nullable();
                $t->string('name');
                $t->string('label', 60)->nullable();
                $t->text('description')->nullable();
                $t->string('url', 500)->nullable();
                $t->text('note')->nullable();
                $t->string('color', 20)->nullable();
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();
            });
            StrictSchema::harden($list, []);
        }

        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        Schema::create('terms', function (Blueprint $t) use ($sqlite) {
            $t->uuid('id')->primary();
            $t->string('slug')->unique();
            $t->string('code', 6)->nullable()->unique();          // Permalink /g/{code} (DEC-026)
            $t->string('official_abbreviation', 60)->nullable()->index();
            $t->string('official_name')->nullable();
            $t->string('short_description', 160)->nullable();
            $t->text('description')->nullable();
            $t->text('note')->nullable();
            $t->string('url', 500)->nullable();                   // Quelle (Link zum Begriff, K1)
            $t->string('lifecycle_status', 20)->default(TermStatus::Current->value);
            $t->boolean('is_active')->default(true);
            $t->date('valid_from')->nullable();
            $t->date('valid_until')->nullable();
            $t->unsignedInteger('revision')->default(0);          // letzte Revision (Zähler, Ausnahme A2)
            $t->uuid('published_revision_id')->nullable()->index();
            $t->foreignUuid('merged_into_id')->nullable()->index()->constrained('terms')->nullOnDelete();
            $t->timestamp('merged_at')->nullable();
            $t->timestamps();
            if ($sqlite) {
                // veröffentlichte Revision gehört zum selben Begriff (DEC-032); PostgreSQL: nach term_revisions
                $t->foreign(['id', 'published_revision_id'], 'terms_published_revision_foreign')
                    ->references(['term_id', 'id'])->on('term_revisions');
            }
        });
        StrictSchema::harden('terms', [
            'terms_lifecycle_status_check' => TermStatus::check('lifecycle_status'),
            'terms_is_active_check' => '"is_active" in (0, 1)',
            'terms_valid_range_check' => '"valid_from" is null or "valid_until" is null or "valid_until" >= "valid_from"',
        ]);

        Schema::create('term_revisions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision');
            $t->string('change_type', 20);
            $t->json('snapshot');
            $t->json('change_set')->nullable();                   // Feld → {old, new}
            $t->string('actor_type', 20)->nullable();
            $t->json('context')->nullable();
            $t->foreignUuid('author_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->string('author_label')->nullable();
            $t->foreignUuid('approved_by_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['term_id', 'revision']);
            $t->unique(['term_id', 'id']);
        });
        StrictSchema::harden('term_revisions', [
            'term_revisions_change_type_check' => ChangeType::check('change_type'),
            'term_revisions_actor_type_check' => ActorType::check('actor_type', true),
        ]);
        if (! $sqlite) {
            Schema::table('terms', function (Blueprint $t) {
                $t->foreign(['id', 'published_revision_id'], 'terms_published_revision_foreign')
                    ->references(['term_id', 'id'])->on('term_revisions')->deferrable()->initiallyImmediate(false);
            });
        }

        Schema::create('term_aliases', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('term_id')->index()->constrained()->cascadeOnDelete();
            $t->string('alias', 120)->index();
            $t->string('alias_type', 20);
            $t->string('language', 10)->default(Locale::De->value);
        });
        StrictSchema::harden('term_aliases', [
            'term_aliases_alias_type_check' => AliasType::check('alias_type'),
            'term_aliases_language_check' => Locale::check('language'),
        ]);

        Schema::create('term_categories', function (Blueprint $t) {
            $t->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('category_id')->index()->constrained()->restrictOnDelete();
            $t->primary(['term_id', 'category_id']);
        });
        StrictSchema::harden('term_categories', []);

        Schema::create('term_sources', function (Blueprint $t) {
            $t->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('source_id')->index()->constrained()->restrictOnDelete();
            $t->date('retrieved_on')->nullable();
            $t->string('note')->nullable();
            $t->string('evidence', 20)->nullable();
            $t->primary(['term_id', 'source_id']);
        });
        StrictSchema::harden('term_sources', ['term_sources_evidence_check' => Evidence::check('evidence', true)]);

        Schema::create('publication_releases', function (Blueprint $t) {
            $t->id();                                             // Nummer des Datenstands
            $t->uuid('term_id')->nullable()->index();             // auslösender Begriff, ohne Fremdschlüssel (Protokoll)
            $t->string('action', 20);
            $t->char('checksum', 64);
            $t->unsignedInteger('term_count');
            $t->foreignUuid('published_by_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
        StrictSchema::harden('publication_releases', ['publication_releases_action_check' => PublicationAction::check('action')]);

        // Change-Feed: Verweise ohne Fremdschlüssel (Ereignisse bleiben), mit Index
        Schema::create('term_publication_events', function (Blueprint $t) {
            $t->id();                                             // Cursor, streng steigend
            $t->uuid('term_id')->index();
            $t->string('action', 20);
            $t->unsignedInteger('revision')->nullable();
            $t->foreignId('release_id')->nullable()->index()->constrained('publication_releases')->nullOnDelete();
            $t->uuid('merged_into_id')->nullable()->index();
            $t->string('actor_type', 20)->nullable();
            $t->string('actor_label', 120)->nullable();
            $t->uuid('actor_id')->nullable()->index();
            $t->timestamp('created_at')->useCurrent();
        });
        StrictSchema::harden('term_publication_events', [
            'term_publication_events_action_check' => PublicationAction::check('action'),
            'term_publication_events_actor_type_check' => ActorType::check('actor_type', true),
        ]);

        Schema::create('duplicate_decisions', function (Blueprint $t) {
            $t->id();
            $t->string('signature', 64)->unique();
            $t->string('match_type', 20);
            $t->string('value');
            $t->json('term_ids');
            $t->foreignUuid('decided_by_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
        StrictSchema::harden('duplicate_decisions', ['duplicate_decisions_match_type_check' => DuplicateMatchType::check('match_type')]);

        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->string('user_label', 120)->nullable();
            $t->string('file_name', 255);
            $t->unsignedTinyInteger('format_version');
            $t->unsignedInteger('rows');
            $t->unsignedInteger('created_count');
            $t->unsignedInteger('updated_count');
            $t->unsignedInteger('error_count');
            $t->json('errors')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
        StrictSchema::harden('import_batches', []);

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 60)->primary();
            $t->json('value');
            $t->uuid('updated_by_id')->nullable()->index();       // ohne Fremdschlüssel (bleibt nach Kontolöschung)
            $t->timestamp('updated_at')->nullable();
        });
        StrictSchema::harden('settings', []);
    }

    private function organizations(): void
    {
        Schema::create('organizations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name', 120)->unique();
            $t->foreignUuid('created_by_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        StrictSchema::harden('organizations', []);

        Schema::create('organization_members', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('role', 20)->default(MemberRole::Member->value);
            $t->string('status', 20)->default(MemberStatus::Active->value);
            $t->boolean('is_role_granted')->default(false);
            $t->timestamps();
            $t->index(['organization_id', 'role']);
        });
        StrictSchema::harden('organization_members', [
            'organization_members_role_check' => MemberRole::check('role'),
            'organization_members_status_check' => MemberStatus::check('status'),
            'organization_members_is_role_granted_check' => '"is_role_granted" in (0, 1)',
        ]);

        Schema::create('organization_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $t->string('email');
            $t->foreignUuid('invited_by_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['organization_id', 'email']);
            $t->index('email');
        });
        StrictSchema::harden('organization_invitations', []);

        Schema::create('organization_term_overrides', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('term_id')->index()->constrained()->cascadeOnDelete();
            $t->string('abbreviation_override', 60)->nullable();
            $t->string('name_override')->nullable();
            $t->string('short_description_override', 160)->nullable();
            $t->text('description_override')->nullable();
            $t->json('aliases_override')->nullable();
            $t->foreignUuid('updated_by_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['organization_id', 'term_id']);
        });
        StrictSchema::harden('organization_term_overrides', []);

        Schema::create('term_user_overrides', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('term_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('user_id')->index()->constrained()->cascadeOnDelete();
            $t->string('abbreviation_override', 60)->nullable();
            $t->string('name_override')->nullable();
            $t->string('short_description_override', 160)->nullable();
            $t->text('description_override')->nullable();
            $t->json('aliases_override')->nullable();
            $t->foreignUuid('approved_organization_id')->nullable()->index()->constrained('organizations')->nullOnDelete();
            $t->foreignUuid('approved_by_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['term_id', 'user_id']);
        });
        StrictSchema::harden('term_user_overrides', []);
    }

    private function services(): void
    {
        Schema::create('service_accounts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name', 80);
            $t->string('purpose', 255);
            $t->foreignUuid('responsible_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->foreignUuid('organization_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $t->string('status', 20)->default(ServiceAccountStatus::Active->value);
            $t->json('scopes');
            $t->timestamps();
        });
        StrictSchema::harden('service_accounts', ['service_accounts_status_check' => ServiceAccountStatus::check('status')]);

        Schema::create('service_account_tokens', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('service_account_id')->index()->constrained('service_accounts')->cascadeOnDelete();
            $t->string('name', 80);
            $t->string('token_hash', 64)->unique();
            $t->string('prefix', 12);
            $t->timestamp('expires_at');
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        StrictSchema::harden('service_account_tokens', []);

        Schema::create('service_account_audit', function (Blueprint $t) {
            $t->id();
            $t->uuid('service_account_id')->nullable()->index();  // ohne Fremdschlüssel: Protokoll bleibt
            $t->uuid('token_id')->nullable()->index();
            $t->uuid('actor_id')->nullable()->index();
            $t->string('actor_label', 120)->nullable();
            $t->string('action', 60);
            $t->json('details')->nullable();
            $t->string('result', 20)->default(AuditResult::Ok->value);
            $t->timestamp('created_at')->useCurrent();
        });
        StrictSchema::harden('service_account_audit', ['service_account_audit_result_check' => AuditResult::check('result')]);

        Schema::create('proposals', function (Blueprint $t) {
            $t->id();                                             // Vorschlags-Nummer
            $t->string('proposal_type', 10);
            $t->foreignUuid('term_id')->nullable()->index()->constrained('terms')->nullOnDelete();
            $t->unsignedInteger('base_revision')->nullable();
            $t->json('changes');
            $t->text('reason')->nullable();
            $t->string('status', 30)->default(ProposalStatus::Submitted->value)->index();
            $t->foreignUuid('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->foreignUuid('service_account_id')->nullable()->index()->constrained('service_accounts')->nullOnDelete();
            $t->foreignUuid('organization_id')->nullable()->index()->constrained()->nullOnDelete();
            $t->string('author_label', 120);
            $t->foreignUuid('result_term_id')->nullable()->index()->constrained('terms')->nullOnDelete();
            $t->unsignedInteger('result_revision')->nullable();
            $t->timestamp('resolved_at')->nullable()->index();
            $t->timestamps();
        });
        StrictSchema::harden('proposals', [
            'proposals_proposal_type_check' => ProposalType::check('proposal_type'),
            'proposals_status_check' => ProposalStatus::check('status'),
        ]);

        Schema::create('proposal_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('proposal_id')->index()->constrained('proposals')->cascadeOnDelete();
            $t->string('event_type', 30);
            $t->uuid('actor_id')->nullable()->index();            // ohne Fremdschlüssel: Verlauf bleibt
            $t->string('actor_label', 120)->nullable();
            $t->boolean('is_by_author')->default(false);
            $t->text('message')->nullable();
            $t->json('details')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        StrictSchema::harden('proposal_events', [
            'proposal_events_event_type_check' => ProposalEventType::check('event_type'),
            'proposal_events_is_by_author_check' => '"is_by_author" in (0, 1)',
        ]);
    }

    private function seedRoles(): void
    {
        $now = now();
        foreach (self::ROLES as [$key, $name, $description, $system, $order, $permissions]) {
            $id = (string) Str::uuid7();
            DB::table('roles')->insert([
                'id' => $id, 'key' => $key, 'name' => $name, 'description' => $description,
                'is_system' => $system, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($permissions as $permission) {
                DB::table('role_permissions')->insert(['role_id' => $id, 'permission' => $permission]);
            }
        }
    }
};
