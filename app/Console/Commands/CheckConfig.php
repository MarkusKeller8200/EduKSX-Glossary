<?php

namespace App\Console\Commands;

use App\Support\ConfigCheck;
use Illuminate\Console\Command;

/**
 * Konfiguration prüfen (#196): fehlende, unsichere oder widersprüchliche Einstellungen, ohne Werte von Geheimnissen.
 * Exit-Code 1 bei Fehlern, damit Installationsskript und Deployment abbrechen können.
 */
class CheckConfig extends Command
{
    protected $signature = 'glossary:pruefen';

    protected $description = 'Konfiguration der Installation prüfen';

    public function handle(): int
    {
        $checks = ConfigCheck::run();
        $symbol = [ConfigCheck::OK => 'ok', ConfigCheck::WARNUNG => 'Warnung', ConfigCheck::FEHLER => 'FEHLER'];
        $this->table(
            ['Bereich', 'Prüfung', 'Ergebnis', 'Hinweis'],
            array_map(fn (array $c): array => [$c['bereich'], $c['pruefung'], $symbol[$c['status']] ?? $c['status'], $c['hinweis']], $checks),
        );

        if (ConfigCheck::hasErrors($checks)) {
            $this->error('Die Konfiguration hat Fehler.');

            return self::FAILURE;
        }
        $this->info('Konfiguration in Ordnung.');

        return self::SUCCESS;
    }
}
