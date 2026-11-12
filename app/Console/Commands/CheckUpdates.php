<?php

namespace App\Console\Commands;

use App\Support\Settings;
use App\Support\UpdateCheck;
use App\Support\UpdateJobs;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Automatische Updates nach Richtlinie (#178): stündlich über den Dienst «scheduler». Legt im Zeitfenster einen
 * Auftrag für den Update-Dienst ab, wenn eine passende neue Version erschienen ist. Ohne aktiven Dienst nichts.
 */
class CheckUpdates extends Command
{
    protected $signature = 'glossary:updates-pruefen';

    protected $description = 'Automatische Updates nach Richtlinie anstossen';

    public function handle(): int
    {
        $policy = Settings::string('updates.policy');
        if ($policy === 'aus' || ! UpdateJobs::serviceActive()) {
            $this->line('Automatische Updates aus oder kein Update-Dienst aktiv.');

            return self::SUCCESS;
        }
        if ((int) now()->format('G') !== Settings::int('updates.window')) {
            $this->line('Ausserhalb des Zeitfensters.');

            return self::SUCCESS;
        }
        $target = UpdateJobs::automaticTarget((string) config('glossary.version'), UpdateCheck::latest(), $policy);
        if ($target === null) {
            $this->line('Keine passende neue Version.');

            return self::SUCCESS;
        }
        try {
            UpdateJobs::request(null, $target, "Richtlinie «{$policy}»");
            $this->info("Auftrag für {$target} abgelegt.");
        } catch (InvalidArgumentException $e) {
            $this->line($e->getMessage());
        }

        return self::SUCCESS;
    }
}
