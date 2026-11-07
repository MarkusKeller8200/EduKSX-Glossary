<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Lokalen Admin anlegen oder dessen Passwort neu setzen (DEC-014). Einziger Weg zu einem Konto: Die Website hat
 * keine Registrierung. Das Passwort wird nur als Hash gespeichert und nie ausgegeben.
 */
class CreateAdmin extends Command
{
    protected $signature = 'glossary:admin
        {email? : E-Mail-Adresse des Kontos}
        {--name= : Anzeigename}
        {--password= : Passwort (nur für Skripte; ohne Angabe wird gefragt)}';

    protected $description = 'Lokalen Admin anlegen bzw. Passwort neu setzen';

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?? $this->ask('E-Mail'));
        $name = (string) ($this->option('name') ?: ($this->ask('Name', 'Administration') ?? 'Administration'));
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
        $user->fill(['name' => $name, 'password' => $password, 'is_admin' => true])->save();

        $this->info($exists ? "Passwort und Rechte für {$user->email} aktualisiert." : "Admin {$user->email} angelegt.");

        return self::SUCCESS;
    }
}
