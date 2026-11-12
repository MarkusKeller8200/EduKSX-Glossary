<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Abwärtskompatible Migrationen (#184, DEC-027 U18): Alte und neue Version laufen beim Update kurz mit derselben
 * Datenbank, und ein Rücksprung startet die alte Version auf der migrierten Datenbank. Darum entfernen oder
 * umbenennen neue Migrationen nichts in derselben Version, in der es noch gelesen wird – erst erweitern, eine
 * Version später aufräumen. Bewusste Ausnahmen tragen im Code den Vermerk «abwaertskompatibel: <Begründung>».
 */
class MigrationCompatibilityTest extends TestCase
{
    /** Migrationen vor der Regel (vor DEC-027) sind ausgenommen. */
    private const AB = '2026_11_12_000000';

    private const VERBOTEN = '/->(dropColumn|renameColumn|dropTable|dropIfExists|rename|change)\s*\(|Schema::(drop|dropIfExists|rename)\s*\(/';

    public function test_neue_migrationen_entfernen_und_benennen_nichts_um(): void
    {
        $verstoesse = [];
        foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') ?: [] as $file) {
            if (strcmp(basename($file), self::AB) < 0) {
                continue;
            }
            $code = (string) file_get_contents($file);
            // nur die Methode up() prüfen – down() darf aufräumen
            $up = preg_match('/function up\(\).*?(?=public function down\(|\z)/s', $code, $m) === 1 ? $m[0] : $code;
            if (preg_match(self::VERBOTEN, $up) === 1 && ! str_contains($up, 'abwaertskompatibel:')) {
                $verstoesse[] = basename($file);
            }
        }

        $this->assertSame([], $verstoesse, 'Nicht abwärtskompatibel (erst erweitern, eine Version später aufräumen): '.implode(', ', $verstoesse));
    }
}
