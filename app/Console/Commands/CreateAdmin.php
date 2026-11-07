<?php

namespace App\Console\Commands;

use App\Access\AccountManager;
use App\Access\RoleManager;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Lokalen Admin anlegen oder dessen Passwort neu setzen (DEC-014). Das Konto erhält die Rolle Admin (nie Developer,
 * dafür glossary:developer). Das Passwort wird nur als Hash gespeichert und nie ausgegeben.
 */
class CreateAdmin extends Command
{
    protected $signature = 'glossary:admin
        {email? : E-Mail-Adresse des Kontos}
        {--name= : Vor- und Nachname}
        {--password= : Passwort (nur für Skripte; ohne Angabe wird gefragt)}
        {--reset-2fa : Nur den zweiten Faktor des Kontos zurücksetzen (z. B. Handy verloren)}
        {--entsperren : Nur ein gesperrtes Konto wieder freigeben (Notfall)}';

    protected $description = 'Lokalen Admin anlegen bzw. Passwort neu setzen';

    public function handle(AccountManager $accounts, RoleManager $roles): int
    {
        $email = (string) ($this->argument('email') ?? $this->ask('E-Mail'));
        if ($this->option('entsperren')) {
            $user = User::query()->where('email', strtolower($email))->first();
            if ($user === null) {
                $this->error("Kein Konto mit {$email}.");

                return self::FAILURE;
            }
            $accounts->unblock(null, $user);
            $this->info("Konto {$user->email} ist wieder aktiv.");

            return self::SUCCESS;
        }
        if ($this->option('reset-2fa')) {
            $user = User::query()->where('email', strtolower($email))->first();
            if ($user === null) {
                $this->error("Kein Konto mit {$email}.");

                return self::FAILURE;
            }
            $accounts->resetTwoFactor(null, $user);
            $this->info("Zweiter Faktor für {$user->email} zurückgesetzt – bei der nächsten Anmeldung neu einrichten.");

            return self::SUCCESS;
        }
        $name = (string) ($this->option('name') ?: ($this->ask('Vor- und Nachname', 'Administration') ?? 'Administration'));
        $password = $this->option('password');
        if (! is_string($password) || $password === '') {
            $password = (string) $this->secret('Passwort (mind. 12 Zeichen)');
            if ($password !== (string) $this->secret('Passwort wiederholen')) {
                $this->error('Die Passwörter stimmen nicht überein.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            ['email' => ['required', 'email'], 'name' => ['required', 'max:100'], 'password' => ['required', Password::defaults()]],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => strtolower($email)]);
        $exists = $user->exists;
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $last = count($parts) > 1 ? (string) array_pop($parts) : '';
        $user->fill(['first_name' => implode(' ', $parts), 'last_name' => $last, 'password' => $password])->save();
        $roles->assign(null, $user, Role::byKey(Role::ADMIN));

        $this->info($exists ? "Passwort und Rechte für {$user->email} aktualisiert." : "Admin {$user->email} angelegt.");

        return self::SUCCESS;
    }
}
