<?php

namespace Tests\Feature;

use App\Support\ConfigCheck;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Selbst hosten (#197, #198): tägliche Kopie der Datenbank und Stand der Sicherung ausser Haus. */
class SelfHostingTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('app/backups');
        File::delete([$this->dir.'/taeglich.sqlite', $this->dir.'/sicherung.json']);
    }

    protected function tearDown(): void
    {
        File::delete([$this->dir.'/taeglich.sqlite', $this->dir.'/sicherung.json']);
        parent::tearDown();
    }

    /** @param  list<array{bereich: string, pruefung: string, status: string, hinweis: string}>  $checks */
    private function ergebnis(array $checks, string $pruefung): string
    {
        foreach ($checks as $c) {
            if ($c['pruefung'] === $pruefung) {
                return $c['status'];
            }
        }
        $this->fail("Prüfung «{$pruefung}» fehlt.");
    }

    public function test_taegliche_kopie_ist_eine_sqlite_datei(): void
    {
        // eigene Datei statt der Testdatenbank im Speicher
        $source = tempnam(sys_get_temp_dir(), 'glossary').'.sqlite';
        (new \PDO('sqlite:'.$source))->exec('CREATE TABLE t (x INTEGER); INSERT INTO t VALUES (1);');
        config(['database.connections.sqlite.database' => $source]);

        $this->artisan('glossary:sicherung')->assertSuccessful();
        @unlink($source);

        $this->assertFileExists($this->dir.'/taeglich.sqlite');
        $this->assertStringStartsWith('SQLite format 3', (string) file_get_contents($this->dir.'/taeglich.sqlite', length: 15));
    }

    public function test_stand_der_sicherung_ausser_haus(): void
    {
        $this->assertSame(ConfigCheck::OK, $this->ergebnis(ConfigCheck::run(), 'Sicherung ausser Haus'));

        File::ensureDirectoryExists($this->dir);
        file_put_contents($this->dir.'/sicherung.json', json_encode(['am' => now()->subDays(3)->toIso8601String(), 'ergebnis' => 'ok']));
        $this->assertSame(ConfigCheck::WARNUNG, $this->ergebnis(ConfigCheck::run(), 'Sicherung ausser Haus'));

        file_put_contents($this->dir.'/sicherung.json', json_encode(['am' => now()->toIso8601String(), 'ergebnis' => 'fehler', 'meldung' => 'Ziel nicht erreichbar']));
        $this->assertSame(ConfigCheck::FEHLER, $this->ergebnis(ConfigCheck::run(), 'Sicherung ausser Haus'));
    }
}
