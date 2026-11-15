<?php

namespace App\Support;

use App\Http\Controllers\Admin\UserController;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserAudit;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Verlauf eines Kontos bzw. einer Organisation (#417): Änderungen an den Angaben aus der Historie (`data_changes`, #286)
 * und Aktionen aus dem Sicherheitsprotokoll (`user_audit`) – wer, wann, was, auch durch Admin und Organisations-Admin.
 * Werte nur nach Datenklasse: personenbezogene Felder erscheinen als «geändert», Geheimnisse nie.
 */
final class History
{
    private const LIMIT = 200;

    /** Spalten → Bezeichnung; nicht aufgeführte Spalten erscheinen nicht. */
    private const FIELDS = [
        'first_name' => 'Vorname', 'last_name' => 'Nachname', 'name' => 'Name', 'email' => 'E-Mail',
        'community_name' => 'Community-Name', 'function' => 'Funktion', 'institution_kind' => 'Art der Einrichtung',
        'institution_name' => 'Einrichtung', 'is_unified_municipality' => 'Einheitsgemeinde', 'status' => 'Status',
        'has_news_opt_in' => 'News', 'privacy_notice_version' => 'Datenschutzerklärung', 'has_password' => 'Passwort',
        'two_factor_confirmed_at' => 'Zweiter Faktor', 'role' => 'Rolle', 'entra_tenant_id' => 'Microsoft-Tenant',
        'platform_id' => 'Plattform-ID', 'is_processing_restricted' => 'Einschränkung', 'deleted_at' => 'Papierkorb',
    ];

    private const ACTIONS = [
        'created' => 'angelegt', 'updated' => 'geändert', 'deleted' => 'gelöscht', 'restored' => 'wiederhergestellt',
    ];

    private const TABLES = [
        'users' => 'Konto', 'organizations' => 'Organisation', 'organization_members' => 'Mitgliedschaft',
        'organization_invitations' => 'Einladung',
    ];

    /** @return list<array{am: string, wer: string, was: string, details: string}> */
    public static function forUser(User $user): array
    {
        $entries = self::changes(DB::table('data_changes')->where(function ($q) use ($user): void {
            $q->where(fn ($q) => $q->where('subject_type', 'users')->where('subject_id', $user->id));
        }));
        foreach (UserAudit::query()->where('target_user_id', $user->id)->latest('id')->limit(self::LIMIT)->get() as $e) {
            $entries[] = self::audit($e);
        }

        return self::sorted($entries);
    }

    /** @return list<array{am: string, wer: string, was: string, details: string}> */
    public static function forOrganization(Organization $organization): array
    {
        return self::sorted(self::changes(DB::table('data_changes')->where(function ($q) use ($organization): void {
            $q->where(fn ($q) => $q->where('subject_type', 'organizations')->where('subject_id', $organization->id))
                ->orWhere(fn ($q) => $q->whereIn('subject_type', ['organization_members', 'organization_invitations'])
                    ->where('organization_id', $organization->id));
        })));
    }

    /**
     * @param  Builder  $query
     * @return list<array{am: string, wer: string, was: string, details: string}>
     */
    private static function changes($query): array
    {
        $out = [];
        foreach ($query->orderByDesc('occurred_at')->limit(self::LIMIT)->get() as $row) {
            $changes = is_string($row->changes) ? (array) json_decode($row->changes, true) : [];
            $fields = [];
            foreach ($changes as $column => $change) {
                if (! isset(self::FIELDS[$column])) {
                    continue;
                }
                $fields[] = self::field((string) $column, $change, (string) $row->action);
            }
            if ($fields === [] && $row->action === 'updated') {
                continue;   // nur technische Spalten
            }
            $out[] = [
                'am' => (string) $row->occurred_at,
                'wer' => self::actor($row->actor_label, $row->actor_type),
                'was' => (self::TABLES[$row->subject_type] ?? $row->subject_type).' '.(self::ACTIONS[$row->action] ?? $row->action),
                'details' => implode(' · ', $fields),
            ];
        }

        return $out;
    }

    private static function field(string $column, mixed $change, string $action): string
    {
        $label = self::FIELDS[$column];
        if (! is_array($change)) {
            return $label.' geändert';   // personenbezogen bzw. geheim: ohne Werte
        }
        $new = self::value($change['new'] ?? null);
        if ($action === 'created') {
            return $label.': '.$new;
        }

        return $label.': '.self::value($change['old'] ?? null).' → '.$new;
    }

    private static function value(mixed $v): string
    {
        return match (true) {
            $v === null || $v === '' => '–',
            $v === true || $v === 1 || $v === '1' => 'ja',
            $v === false || $v === 0 || $v === '0' => 'nein',
            is_scalar($v) => mb_strimwidth((string) $v, 0, 80, '…'),
            default => '…',
        };
    }

    /** @return array{am: string, wer: string, was: string, details: string} */
    private static function audit(UserAudit $e): array
    {
        $details = is_array($e->new_values) ? $e->new_values : [];
        $text = [];
        foreach (['reason' => 'Grund', 'organization' => 'Organisation', 'durch' => 'durch'] as $key => $label) {
            if (isset($details[$key]) && is_scalar($details[$key])) {
                $text[] = $label.': '.$details[$key];
            }
        }
        if ($e->result !== 'ok') {
            $text[] = 'abgelehnt';
        }

        return [
            'am' => $e->created_at->utc()->toDateTimeString(),
            'wer' => $e->actor_id !== null ? (string) (User::query()->whereKey($e->actor_id)->value('name') ?? 'gelöschtes Konto') : (string) ($e->actor_label ?? 'System'),
            'was' => UserController::ACTIONS[$e->action] ?? $e->action,
            'details' => implode(' · ', $text),
        ];
    }

    private static function actor(?string $label, ?string $type): string
    {
        if ($label !== null && $label !== '') {
            return $label;
        }

        return $type === 'system' || $type === null ? 'System' : $type;
    }

    /**
     * @param  list<array{am: string, wer: string, was: string, details: string}>  $entries
     * @return list<array{am: string, wer: string, was: string, details: string}>
     */
    private static function sorted(array $entries): array
    {
        usort($entries, fn (array $a, array $b): int => strcmp($b['am'], $a['am']));

        return array_slice($entries, 0, self::LIMIT);
    }
}
