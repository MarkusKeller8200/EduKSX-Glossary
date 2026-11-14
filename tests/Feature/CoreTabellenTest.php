<?php

namespace Tests\Feature;

use EduKSX\Core\Data\DataClasses as CoreDataClasses;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Gemeinsame Tabellen aus eduksx/core (DB-E12, #329): Aufbau im Glossary gleich wie in Core. */
final class CoreTabellenTest extends TestCase
{
    public function test_spalten_wie_in_core(): void
    {
        foreach (['data_changes', 'received_events'] as $table) {
            $core = array_keys(CoreDataClasses::CORE[$table]);
            $glossary = Schema::getColumnListing($table);
            sort($core);
            sort($glossary);
            $this->assertSame($core, $glossary, $table);
        }
    }
}
