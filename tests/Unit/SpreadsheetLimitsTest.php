<?php

namespace Tests\Unit;

use App\Support\SpreadsheetReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

/** Excel-Import: stark komprimierte Dateien werden abgewiesen, bevor sie entpackt werden (#448, SEC-06). */
final class SpreadsheetLimitsTest extends TestCase
{
    public function test_zip_bombe_wird_abgewiesen(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', str_repeat('0', SpreadsheetReader::MAX_UNPACKED_BYTES + 1024));
        $zip->close();
        $this->assertLessThan(1024 * 1024, filesize($path));   // klein gepackt

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('zu gross');
            SpreadsheetReader::read($path, 'bombe.xlsx', ['name']);
        } finally {
            @unlink($path);
        }
    }
}
