<?php

namespace App\Data;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Plattformweit eindeutige Ereignis-ID (UUID v7, DB-Standard A3, #332) neben der fortlaufenden Zahl: Damit lassen sich
 * Ereignisse und Protokolle verschiedener Module (Leitstand, Löschregister) zusammenführen, ohne dass Zahlen kollidieren.
 */
trait HasEventId
{
    public static function bootHasEventId(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('event_id') === null) {
                $model->setAttribute('event_id', (string) Str::uuid7());
            }
        });
    }
}
