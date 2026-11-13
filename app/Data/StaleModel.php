<?php

namespace App\Data;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** Datensatz wurde inzwischen von jemand anderem geändert (optimistisches Sperren, #286); HTTP 409. */
final class StaleModel extends RuntimeException
{
    public function __construct(public readonly Model $model)
    {
        parent::__construct('Der Eintrag wurde inzwischen von jemand anderem geändert. Bitte neu laden und erneut speichern.');
    }
}
