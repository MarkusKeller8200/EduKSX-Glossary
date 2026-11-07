<?php

namespace App\Console\Commands;

use App\Access\AccessDenied;
use App\Access\RoleManager;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Gesicherter Serverprozess für die Developer-Rolle (#36, docs/KONZEPT-BENUTZER.md Abschnitt 3): Vergabe und Entzug
 * nur hier oder durch einen Developer; jede Ausführung steht im Audit-Protokoll. Auch Notfallzugang, wenn kein
 * Developer mehr erreichbar ist.
 */
class AssignDeveloper extends Command
{
    protected $signature = 'glossary:developer
        {email : E-Mail-Adresse eines bestehenden Kontos}
        {--entziehen : Rolle Developer entziehen statt vergeben}
        {--grund= : Begründung für das Protokoll}';

    protected $description = 'Rolle Developer vergeben oder entziehen (protokolliert)';

    public function handle(RoleManager $roles): int
    {
        $user = User::query()->where('email', strtolower((string) $this->argument('email')))->first();
        if ($user === null) {
            $this->error('Kein Konto mit dieser E-Mail. Zuerst mit glossary:admin anlegen.');

            return self::FAILURE;
        }
        $reason = trim((string) $this->option('grund'));
        $label = RoleManager::SERVER.($reason !== '' ? ': '.mb_substr($reason, 0, 100) : '');

        try {
            if ($this->option('entziehen')) {
                $roles->revoke(null, $user, Role::byKey(Role::DEVELOPER), $label);
                $this->info("Developer für {$user->email} entzogen.");
            } else {
                $roles->assign(null, $user, Role::byKey(Role::DEVELOPER), $label);
                $this->info("{$user->email} ist jetzt Developer.".($user->hasTwoFactor() ? '' : ' Bei der nächsten Anmeldung den zweiten Faktor einrichten.'));
            }
        } catch (AccessDenied $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
