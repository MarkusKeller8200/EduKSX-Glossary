<?php

namespace App\Console\Commands;

use App\Access\AccountManager;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Bearbeitung eines Kontos einschränken bzw. freigeben (#312, DB-Standard B13), Serverbefehl (Akteur «Server»). */
class RestrictProcessing extends Command
{
    protected $signature = 'glossary:einschraenken {konto : E-Mail-Adresse oder ID} {--aufheben : Einschränkung aufheben}';

    protected $description = 'Bearbeitung eines Kontos einschränken (Daten bleiben, werden nicht angezeigt, weitergegeben oder geändert)';

    public function handle(AccountManager $accounts): int
    {
        $key = (string) $this->argument('konto');
        $user = Str::isUuid($key)
            ? User::query()->find($key)
            : User::query()->whereRaw('lower(email) = ?', [mb_strtolower($key)])->first();
        if ($user === null) {
            $this->error('Konto nicht gefunden.');

            return self::FAILURE;
        }
        $restrict = ! $this->option('aufheben');
        $accounts->restrictProcessing(null, $user, $restrict);
        $this->info($restrict ? 'Bearbeitung eingeschränkt.' : 'Einschränkung aufgehoben.');

        return self::SUCCESS;
    }
}
