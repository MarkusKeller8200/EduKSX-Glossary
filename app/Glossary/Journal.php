<?php

namespace App\Glossary;

use App\Models\ImportBatch;
use App\Models\Role;
use App\Models\TermPublicationEvent;
use App\Models\TermRevision;
use App\Models\TermUserOverride;
use App\Models\User;

/**
 * Änderungsjournal des Glossary (DEC-020): wer hat wann was geändert, feldgenau. Nur für Redaktion und
 * Administration, nie öffentlich (kein Feldprotokoll in API, Export, Change-Feed, GitHub Pages).
 *
 * - Bezeichnung des Akteurs ist ein Text zum Zeitpunkt der Änderung (Community-Name, sonst Name), kein Bezug.
 * - Der technische Bezug (author_id, actor_id) wird bei der Kontolöschung leer, das Journal bleibt bestehen.
 */
final class Journal
{
    /** Ersatz der Bezeichnung nach der Kontolöschung. */
    public const DELETED_LABEL = 'Gelöschtes Konto';

    /** Rolle des Akteurs zum Ereigniszeitpunkt (höchste Rolle zählt). */
    public static function actorType(?User $user): string
    {
        if ($user === null) {
            return 'system';
        }
        $roles = $user->roleKeys();

        return match (true) {
            in_array(Role::DEVELOPER, $roles, true) => 'developer',
            in_array(Role::ADMIN, $roles, true) => 'admin',
            in_array(Role::MODERATOR, $roles, true) => 'moderator',
            default => 'community',
        };
    }

    /** Anzeigename im Journal: Community-Bezeichnung, sonst Name. */
    public static function actorLabel(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }
        $label = trim((string) ($user->community_name ?: $user->name));

        return $label === '' ? null : mb_substr($label, 0, 120);
    }

    /**
     * Akteur für ein Veröffentlichungsereignis.
     *
     * @return array{actor_type: string, actor_label: string|null, actor_id: string|null}
     */
    public static function eventActor(?User $user): array
    {
        return ['actor_type' => self::actorType($user), 'actor_label' => self::actorLabel($user), 'actor_id' => $user?->id];
    }

    /**
     * Feldgenaue Änderungen zwischen zwei Abbildern. Leer, null und [] gelten als gleich; Listen sind im Abbild
     * bereits sortiert. Beim Anlegen ($before = null) erscheinen alle gesetzten Felder mit altem Wert null.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function diff(?array $before, array $after): array
    {
        $changes = [];
        foreach ($after + ($before ?? []) as $field => $_) {
            if ($field === 'id') {
                continue;
            }
            $old = self::blank($before[$field] ?? null) ? null : $before[$field];
            $new = self::blank($after[$field] ?? null) ? null : $after[$field];
            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    /**
     * Kontolöschung (DEC-020, docs/LOESCHKONZEPT.md): persönliche Darstellungen löschen, die Bezeichnung im Journal
     * durch «Gelöschtes Konto» ersetzen, technische Bezüge leeren. Inhalt, Zeit und Änderungswerte bleiben.
     * Bezeichnungen, die nicht die Person nennen (z. B. «Excel-Import»), bleiben stehen.
     */
    public static function forget(User $user): void
    {
        TermUserOverride::query()->where('user_id', $user->id)->delete();

        $labels = array_values(array_filter(array_unique([
            trim((string) $user->name), trim((string) $user->community_name), (string) self::actorLabel($user),
        ]), fn (string $l): bool => $l !== ''));

        // Ausnahme von der Unveränderlichkeit der Revisionen: nur Bezeichnung und Bezug (ohne Model-Ereignisse)
        TermRevision::query()->where('author_id', $user->id)->whereIn('author_label', $labels)->toBase()
            ->update(['author_label' => self::DELETED_LABEL]);
        TermRevision::query()->where('author_id', $user->id)->toBase()->update(['author_id' => null]);
        TermRevision::query()->where('approved_by_id', $user->id)->toBase()->update(['approved_by_id' => null]);

        TermPublicationEvent::query()->where('actor_id', $user->id)->whereIn('actor_label', $labels)->toBase()
            ->update(['actor_label' => self::DELETED_LABEL]);
        TermPublicationEvent::query()->where('actor_id', $user->id)->toBase()->update(['actor_id' => null]);
        ImportBatch::query()->where('user_id', $user->id)->toBase()->update(['user_label' => self::DELETED_LABEL, 'user_id' => null]);
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
