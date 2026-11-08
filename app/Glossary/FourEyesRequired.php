<?php

namespace App\Glossary;

use App\Models\Term;
use InvalidArgumentException;

/** Vier-Augen-Prinzip (#106, DEC-022): die letzte Änderung muss eine andere Person freigeben. */
final class FourEyesRequired extends InvalidArgumentException
{
    public function __construct(public readonly Term $term)
    {
        parent::__construct('Vier-Augen-Prinzip: Die letzte Änderung an «'.$term->title().'» muss eine andere Person freigeben.');
    }
}
