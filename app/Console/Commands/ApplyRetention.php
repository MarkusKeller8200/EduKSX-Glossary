<?php

namespace App\Console\Commands;

use App\Access\Audit;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Löschfristen anwenden (docs/DATENSCHUTZ-ENTWURF.md 7, #41/#42). Läuft täglich über den Dienst «scheduler».
 * Hier: unbestätigte Registrierungen nach 7 Tagen.
 */
class ApplyRetention extends Command
{
    protected $signature = 'glossary:loeschfristen {--trocken : Nur anzeigen, nichts löschen}';

    protected $description = 'Löschfristen anwenden (unbestätigte Registrierungen u. a.)';

    public function handle(): int
    {
        $pending = User::query()->where('status', 'pending')->where('created_at', '<', now()->subDays(7))->get();
        foreach ($pending as $user) {
            if (! $this->option('trocken')) {
                Audit::log(null, $user, 'account.deleted', ['reason' => 'Registrierung nicht bestätigt (7 Tage)'], 'ok', 'Löschfrist');
                $user->delete();
            }
        }
        $this->info(($this->option('trocken') ? 'Würde löschen: ' : 'Gelöscht: ').$pending->count().' unbestätigte Registrierungen.');

        return self::SUCCESS;
    }
}
