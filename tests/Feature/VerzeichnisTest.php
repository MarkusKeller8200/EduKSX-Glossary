<?php

namespace Tests\Feature;

use App\Console\Commands\ProcessingRecord;
use App\Data\DataClasses;
use App\Data\Purposes;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Zweck je Tabelle und Verzeichnis der Bearbeitungen (#314, DB-Standard B9, API-Standard C9). */
final class VerzeichnisTest extends TestCase
{
    public function test_jede_tabelle_hat_genau_einen_zweck(): void
    {
        $tables = [];
        foreach (Schema::getTables() as $info) {
            $table = $info['name'];
            if (! str_starts_with($table, 'sqlite_') && ! in_array($table, DataClasses::FRAMEWORK, true)) {
                $tables[] = $table;
            }
        }
        $assigned = array_merge(...array_column(Purposes::MAP, 'tables'));
        sort($tables);
        $sorted = $assigned;
        sort($sorted);

        $this->assertSame($tables, $sorted, 'Tabellen ohne Zweck bzw. Zwecke für nicht vorhandene Tabellen');
        $this->assertSame(count($assigned), count(array_unique($assigned)), 'Tabelle mehreren Zwecken zugeordnet');
        foreach (Purposes::MAP as $key => $purpose) {
            foreach (['purpose', 'basis', 'scope', 'subjects', 'recipients', 'retention'] as $field) {
                $this->assertNotSame('', trim($purpose[$field]), "{$key}.{$field} fehlt");
            }
        }
    }

    public function test_jede_operation_der_api_hat_einen_zweck(): void
    {
        $spec = json_decode((string) file_get_contents(base_path('openapi/glossary-v1.json')), true);
        $operations = [];
        foreach ($spec['paths'] as $methods) {
            foreach ($methods as $operation) {
                if (is_array($operation) && isset($operation['operationId'])) {
                    $operations[] = $operation['operationId'];
                }
            }
        }
        $assigned = array_merge(...array_column(Purposes::MAP, 'operations'));
        sort($operations);
        sort($assigned);

        $this->assertSame($operations, $assigned);
    }

    public function test_verzeichnis_ist_aktuell(): void
    {
        $this->assertSame(ProcessingRecord::render(), (string) file_get_contents(base_path(ProcessingRecord::PATH)),
            'docs/DATENSCHUTZ-VERZEICHNIS.md veraltet: php artisan glossary:verzeichnis');
    }
}
