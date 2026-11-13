<?php

namespace App\Data;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Papierkorb (B3 DB-Standard, #286) für Hauptobjekte: vorläufig gelöscht mit deleted_at und deleted_by_id,
 * endgültig nach der Frist (glossary:loeschfristen). Wiederherstellen dürfen nur Developer (Super-Admin, W5).
 *
 * @property CarbonImmutable|null $deleted_at
 * @property string|null $deleted_by_id
 */
trait Trashable
{
    use SoftDeletes;

    /** Tage im Papierkorb bis zur endgültigen Löschung (Betrieb: GLOSSARY_TRASH_DAYS). */
    public static function trashDays(): int
    {
        return max(1, (int) config('glossary.trash_days', 30));
    }

    protected function runSoftDelete(): void
    {
        $user = auth()->user();
        $by = $user instanceof User ? $user->id : null;
        $query = $this->setKeysForSaveQuery($this->newModelQuery());
        $time = $this->freshTimestamp();
        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time), 'deleted_by_id' => $by];
        $this->{$this->getDeletedAtColumn()} = $time;
        $this->setAttribute('deleted_by_id', $by);
        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;
            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }
        $query->update($columns);
        $this->syncOriginalAttributes(array_keys($columns));
        $this->fireModelEvent('trashed', false);
    }

    /** Wiederherstellen: auch den Bezug auf die löschende Person entfernen. */
    public function restoreFromTrash(): void
    {
        $this->setAttribute('deleted_by_id', null);
        $this->restore();
    }
}
