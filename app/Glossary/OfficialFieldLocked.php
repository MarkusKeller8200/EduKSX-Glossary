<?php

namespace App\Glossary;

use RuntimeException;

/** Offizielles Feld ohne Recht «edit-official» geändert (Regel aus dem Intranet-Glossar). */
final class OfficialFieldLocked extends RuntimeException
{
    public function __construct(public readonly string $field)
    {
        parent::__construct("Das offizielle Feld «{$field}» lässt sich nur mit dem Recht «offizielle Begriffe pflegen» ändern.");
    }
}
