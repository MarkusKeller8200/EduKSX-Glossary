<?php

namespace App\Access\Service;

use App\Access\AccessDenied;
use App\Models\ServiceAccount;
use App\Models\ServiceAccountAudit;
use App\Models\ServiceAccountToken;
use App\Models\User;
use App\Organizations\OrganizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Einzige Stelle, die Servicekonten und Token ändert und Token prüft (#47, KONZEPT-BENUTZER 2.2, DEC-017).
 *
 * - Anlegen ab Recht «service-tokens» (Organisations-Admin, Admin); verantwortlich ist die anlegende Person.
 * - Verwalten: die verantwortliche Person, mit «manage-users» alle Servicekonten.
 * - Scopes nur innerhalb der eigenen Rechte (z. B. «propose» nur, wer selbst Vorschläge einreichen darf).
 * - Scope «read-org» (#148): nur Organisations-Admins; das Servicekonto gehört dann der Organisation und liest die
 *   Begriffe in deren Darstellung. Alle Organisations-Admins dieser Organisation verwalten es.
 * - Token: 40 zufällige Zeichen mit Präfix «gls_», nur einmal angezeigt, gespeichert als SHA-256-Hash, höchstens
 *   ein Jahr gültig, einzeln widerrufbar. Kein Login, keine Sitzung.
 *
 * Jede Änderung und jeder abgewiesene Zugriff mit bekanntem Token landet im Protokoll.
 */
final class ServiceAccountManager
{
    /**
     * Scopes → Bezeichnung, nach API-Standard `<modul>:<ressource>.<aktion>` (C2, #323). Schreibende Endpunkte prüfen
     * den Scope zusätzlich zur Geschäftslogik.
     */
    public const SCOPES = [
        'glossary:proposals.create' => 'Vorschläge einreichen (landen in der Moderation, nie direkt öffentlich)',
        'glossary:proposals.read' => 'Eigene Vorschläge und ihren Stand lesen',
        'glossary:terms.read' => 'Begriffe in der Darstellung der eigenen Organisation lesen (nur Organisations-Admins)',
    ];

    /** Scopes bis 0.13 → neue Namen; gespeicherte alte Namen gelten weiter (#323). */
    public const LEGACY_SCOPES = [
        'propose' => ['glossary:proposals.create', 'glossary:proposals.read'],
        'read-org' => ['glossary:terms.read'],
    ];

    /** Recht der handelnden Person, das ein Scope voraussetzt (null: Organisations-Admin, ORG_SCOPE). */
    private const SCOPE_PERMISSIONS = [
        'glossary:proposals.create' => 'propose',
        'glossary:proposals.read' => 'propose',
        'glossary:terms.read' => null,
    ];

    /** Scope der Organisations-Sicht (#148): Die Organisation kommt aus dem Servicekonto. */
    public const ORG_SCOPE = 'glossary:terms.read';

    /** Token-Präfix nach API-Standard C1 (`<modul>_sa_`); Tokens mit `gls_` gelten bis zu ihrem Ablauf. */
    public const PREFIX = 'glossary_sa_';

    public const LEGACY_PREFIX = 'gls_';

    /**
     * Scopes inklusive der neuen Namen für gespeicherte alte (propose, read-org).
     *
     * @param  list<string>  $scopes
     * @return list<string>
     */
    public static function expand(array $scopes): array
    {
        $out = [];
        foreach ($scopes as $scope) {
            $out = array_merge($out, [$scope], self::LEGACY_SCOPES[$scope] ?? []);
        }

        return array_values(array_unique($out));
    }

    /**
     * Für die Anzeige: nur die heutigen Namen.
     *
     * @param  list<string>  $scopes
     * @return list<string>
     */
    public static function current(array $scopes): array
    {
        return array_values(array_intersect(array_keys(self::SCOPES), self::expand($scopes)));
    }

    public function __construct(private readonly OrganizationService $organizations) {}

    public const MAX_DAYS = 365;

    /**
     * Servicekonten, die $user sehen und verwalten darf.
     *
     * @return Builder<ServiceAccount>
     */
    public function visibleFor(User $user): Builder
    {
        $organization = $this->adminOrganization($user);

        return ServiceAccount::query()->when(! $user->hasPermission('manage-users'), fn ($q) => $q->where(function ($q) use ($user, $organization): void {
            $q->where('responsible_user_id', $user->id);
            if ($organization !== null) {
                $q->orWhere('organization_id', $organization);
            }
        }));
    }

    public function canManage(User $user, ServiceAccount $account): bool
    {
        return $user->hasPermission('service-tokens')
            && ($user->hasPermission('manage-users') || $account->responsible_user_id === $user->id
                || ($account->organization_id !== null && $account->organization_id === $this->adminOrganization($user)));
    }

    /** ID der Organisation, die $user als aktiver Organisations-Admin verwaltet. */
    private function adminOrganization(User $user): ?string
    {
        $member = $this->organizations->membershipOf($user);

        return $member !== null && $member->isAdmin() && $member->isActive() ? $member->organization_id : null;
    }

    /** @param  list<string>  $scopes */
    public function create(User $actor, string $name, string $purpose, array $scopes): ServiceAccount
    {
        // alte Namen umsetzen, unbekannte für die Prüfung behalten
        $normalized = [];
        foreach ($scopes as $scope) {
            array_push($normalized, ...(self::LEGACY_SCOPES[$scope] ?? [$scope]));
        }
        $scopes = array_values(array_unique($normalized));
        $details = ['name' => $name, 'scopes' => $scopes];
        $organization = null;
        try {
            if (! $actor->hasPermission('service-tokens')) {
                throw new AccessDenied('Dafür fehlt das Recht «Servicekonten und Token verwalten».');
            }
            $this->requireScopes($actor, $scopes);
            $organization = in_array(self::ORG_SCOPE, $scopes, true) ? $this->adminOrganization($actor) : null;
            if (in_array(self::ORG_SCOPE, $scopes, true) && $organization === null) {
                throw new AccessDenied('Scope «'.self::ORG_SCOPE.'» nur für Organisations-Admins einer Organisation.');
            }
        } catch (AccessDenied $e) {
            $this->log($actor, null, null, 'service.created', ['reason' => $e->getMessage()] + $details, 'denied');

            throw $e;
        }

        $account = ServiceAccount::query()->create([
            'name' => $name, 'purpose' => $purpose, 'responsible_user_id' => $actor->id,
            'status' => 'active', 'scopes' => $scopes, 'organization_id' => $organization,
        ]);
        $this->log($actor, $account, null, 'service.created', $details);

        return $account;
    }

    /**
     * Neues Token; der Klartext wird nur hier zurückgegeben.
     *
     * @return array{token: ServiceAccountToken, plain: string}
     */
    public function createToken(User $actor, ServiceAccount $account, string $name, int $days): array
    {
        $this->guard($actor, $account, null, 'token.created', function () use ($actor, $account, $days): void {
            $this->requireManage($actor, $account);
            if (! $account->isActive()) {
                throw new AccessDenied('Das Servicekonto ist gesperrt.');
            }
            if ($days < 1 || $days > self::MAX_DAYS) {
                throw new AccessDenied('Gültigkeit 1 bis '.self::MAX_DAYS.' Tage.');
            }
        }, ['name' => $name, 'days' => $days]);

        $plain = self::PREFIX.Str::random(40);
        $token = $account->tokens()->create([
            // Anfang des zufälligen Teils zum Wiedererkennen (Anzeige mit Präfix)
            'name' => $name, 'token_hash' => hash('sha256', $plain), 'prefix' => substr($plain, strlen(self::PREFIX), 8),
            'expires_at' => now()->addDays($days),
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    public function revokeToken(User $actor, ServiceAccountToken $token): void
    {
        $account = $token->account;
        $this->guard($actor, $account, $token, 'token.revoked', fn () => $this->requireManage($actor, $account));
        if ($token->revoked_at === null) {
            $token->forceFill(['revoked_at' => now()])->save();
        }
    }

    public function setStatus(User $actor, ServiceAccount $account, bool $active): void
    {
        $this->guard($actor, $account, null, $active ? 'service.enabled' : 'service.disabled', fn () => $this->requireManage($actor, $account));
        $account->forceFill(['status' => $active ? 'active' : 'disabled'])->save();
    }

    /**
     * Token aus dem Header «Authorization: Bearer …» prüfen. Gibt das Token nur zurück, wenn es gültig ist, das Konto
     * aktiv ist und (falls angegeben) den Scope hat; sonst null (ein bekanntes, aber ungültiges Token wird protokolliert).
     */
    public function authenticate(?string $plain, ?string $scope = null): ?ServiceAccountToken
    {
        if ($plain === null || (! str_starts_with($plain, self::PREFIX) && ! str_starts_with($plain, self::LEGACY_PREFIX))) {
            return null;
        }
        $token = ServiceAccountToken::query()->where('token_hash', hash('sha256', $plain))->with('account')->first();
        if ($token === null) {
            return null;
        }
        $reason = match (true) {
            $token->revoked_at !== null => 'widerrufen',
            ! $token->expires_at->isFuture() => 'abgelaufen',
            ! $token->account->isActive() => 'Konto gesperrt',
            $scope !== null && ! $token->account->hasScope($scope) => "Scope «{$scope}» fehlt",
            default => null,
        };
        if ($reason !== null) {
            $this->log(null, $token->account, $token, 'token.used', ['scope' => $scope, 'reason' => $reason], 'denied');

            return null;
        }
        // Zeitpunkt höchstens einmal pro Minute schreiben
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        return $token;
    }

    // ---------- Regeln ----------

    private function requireManage(User $actor, ServiceAccount $account): void
    {
        if (! $this->canManage($actor, $account)) {
            throw new AccessDenied('Dieses Servicekonto verwaltet die verantwortliche Person oder ein Admin.');
        }
    }

    /** @param  list<string>  $scopes */
    private function requireScopes(User $actor, array $scopes): void
    {
        foreach ($scopes as $scope) {
            if (! isset(self::SCOPES[$scope])) {
                throw new AccessDenied("Unbekannter Scope «{$scope}».");
            }
            $permission = self::SCOPE_PERMISSIONS[$scope];
            if ($permission !== null && ! $actor->hasPermission($permission)) {
                throw new AccessDenied("Scope «{$scope}» geht über die eigenen Rechte hinaus.");
            }
        }
    }

    /**
     * @param  callable(): void  $rules
     * @param  array<mixed>|null  $details
     */
    private function guard(User $actor, ?ServiceAccount $account, ?ServiceAccountToken $token, string $action, callable $rules, ?array $details = null): void
    {
        try {
            $rules();
        } catch (AccessDenied $e) {
            $this->log($actor, $account, $token, $action, ['reason' => $e->getMessage()] + ($details ?? []), 'denied');

            throw $e;
        }
        $this->log($actor, $account, $token, $action, $details);
    }

    /** @param  array<mixed>|null  $details */
    private function log(?User $actor, ?ServiceAccount $account, ?ServiceAccountToken $token, string $action, ?array $details, string $result = 'ok'): void
    {
        ServiceAccountAudit::query()->create([
            'service_account_id' => $account?->id,
            'token_id' => $token?->id,
            'actor_id' => $actor?->id,
            'actor_label' => $actor === null ? 'Token' : null,
            'action' => $action,
            'details' => $details,
            'result' => $result,
        ]);
    }
}
