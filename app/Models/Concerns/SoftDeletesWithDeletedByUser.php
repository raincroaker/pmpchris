<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Expects implementing models to define a nullable `deleted_by_user_id` column.
 *
 * @property int|null $deleted_by_user_id
 */
trait SoftDeletesWithDeletedByUser
{
    use SoftDeletes;

    public static function bootSoftDeletesWithDeletedByUser(): void
    {
        static::restoring(function (Model $model): void {
            $model->deleted_by_user_id = null;
        });
    }

    protected function runSoftDelete(): void
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $userId = Auth::id();
        if ($userId !== null) {
            $columns['deleted_by_user_id'] = $userId;
            $this->deleted_by_user_id = $userId;
        }

        $query->update($columns);

        $this->syncOriginalAttributes(array_keys($columns));

        $this->fireModelEvent('trashed', false);
    }
}
