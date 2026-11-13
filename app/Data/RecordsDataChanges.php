<?php

namespace App\Data;

use Illuminate\Database\Eloquent\Model;

/**
 * Schreibt jede Änderung des Modells in die Historie (data_changes, #286). Zugriffe am Modell vorbei
 * (DB::table()->update(), Migrationen) schreiben selbst einen Eintrag über DataChanges::write().
 */
trait RecordsDataChanges
{
    public static function bootRecordsDataChanges(): void
    {
        static::created(fn (Model $model) => DataChanges::record($model, 'created'));
        static::updated(fn (Model $model) => DataChanges::record($model, 'updated'));
        static::deleted(fn (Model $model) => DataChanges::record($model, 'deleted'));
        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => DataChanges::record($model, 'restored'));
        }
    }
}
