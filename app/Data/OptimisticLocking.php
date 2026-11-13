<?php

namespace App\Data;

use Illuminate\Database\Eloquent\Builder;

/**
 * Optimistisches Sperren (A6 DB-Standard, #286): gespeichert wird nur, wenn lock_version noch dem gelesenen Stand
 * entspricht; jede Änderung erhöht sie. Sonst StaleModel – nichts wird überschrieben.
 *
 * @property int $lock_version
 */
trait OptimisticLocking
{
    /** Erwarteten Stand prüfen (z. B. aus einem Formular); null = nicht prüfen. */
    public function assertLockVersion(mixed $expected): void
    {
        if ($expected !== null && $expected !== '' && (int) $expected !== (int) $this->getAttribute('lock_version')) {
            throw new StaleModel($this);
        }
    }

    /**
     * @param  Builder<static>  $query
     */
    protected function performUpdate(Builder $query): bool
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }
        $dirty = $this->getDirtyForUpdate();
        if (count($dirty) > 0) {
            $current = (int) $this->getOriginal('lock_version');
            $this->setAttribute('lock_version', $current + 1);
            $dirty['lock_version'] = $current + 1;
            $affected = $this->setKeysForSaveQuery($query)->where('lock_version', $current)->update($dirty);
            if ($affected === 0) {
                $this->setAttribute('lock_version', $current);
                throw new StaleModel($this);
            }
            $this->syncChanges();
            $this->fireModelEvent('updated', false);
        }

        return true;
    }
}
