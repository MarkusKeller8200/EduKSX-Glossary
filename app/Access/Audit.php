<?php

namespace App\Access;

use App\Models\User;
use App\Models\UserAudit;

/** Kurzform für Einträge im Audit-Protokoll (Konten, Anmeldung, zweiter Faktor). */
final class Audit
{
    /**
     * @param  array<mixed>|null  $new
     */
    public static function log(?User $actor, ?User $target, string $action, ?array $new = null, string $result = 'ok', ?string $label = null): void
    {
        UserAudit::query()->create([
            'actor_id' => $actor?->id,
            'actor_label' => $actor === null ? ($label ?? RoleManager::SERVER) : $label,
            'target_user_id' => $target?->id,
            'action' => $action,
            'new' => $new,
            'result' => $result,
        ]);
    }
}
