<?php

namespace Tests\Feature;

use App\Database\PostgresSchema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Schema je Modul (#333, DB-Standard E2): auf PostgreSQL liegen alle Tabellen im Schema «glossary». */
final class PostgresSchemaTest extends TestCase
{
    public function test_alle_tabellen_im_schema_glossary(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('nur PostgreSQL');
        }
        $this->assertSame('glossary', PostgresSchema::name());

        $outside = DB::select("select table_schema, table_name from information_schema.tables
            where table_type = 'BASE TABLE' and table_schema not in ('glossary', 'pg_catalog', 'information_schema')");
        $this->assertSame([], array_map(fn (object $t): string => "{$t->table_schema}.{$t->table_name}", $outside), 'Tabellen ausserhalb von glossary');

        $inside = array_column(DB::select("select table_name from information_schema.tables where table_schema = 'glossary'"), 'table_name');
        foreach (['migrations', 'terms', 'jobs', 'cache', 'sessions', 'data_changes'] as $table) {
            $this->assertContains($table, $inside);
        }
    }
}
