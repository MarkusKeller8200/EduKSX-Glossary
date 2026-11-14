<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Plattform-ID zuordnen (#311, DB-Standard E3): Organisation bzw. Konto mit der ID aus EduKSX-Ident verknüpfen, bis
 * der Anschluss an Ident das automatisch erledigt (#186). Ohne ID: aktuellen Stand anzeigen.
 */
class AssignPlatformId extends Command
{
    protected $signature = 'glossary:plattform-id
        {art : organisation oder konto}
        {glossary_id : UUID der Organisation bzw. des Kontos im Glossary}
        {platform_id? : UUID aus EduKSX-Ident; leer = nur anzeigen}
        {--entfernen : Zuordnung aufheben}';

    protected $description = 'Plattform-ID einer Organisation oder eines Kontos zuordnen (#311)';

    public function handle(): int
    {
        $model = match ((string) $this->argument('art')) {
            'organisation' => Organization::query()->find($this->argument('glossary_id')),
            'konto' => User::query()->find($this->argument('glossary_id')),
            default => false,
        };
        if ($model === false) {
            $this->error('Art muss «organisation» oder «konto» sein.');

            return self::INVALID;
        }
        if ($model === null) {
            $this->error('Nicht gefunden.');

            return self::FAILURE;
        }
        $platformId = $this->argument('platform_id');
        if ($this->option('entfernen')) {
            $model->forceFill(['platform_id' => null])->save();
            $this->info('Zuordnung aufgehoben.');

            return self::SUCCESS;
        }
        if ($platformId === null) {
            $this->line('Plattform-ID: '.($model->getAttribute('platform_id') ?? '–'));

            return self::SUCCESS;
        }
        if (! Str::isUuid((string) $platformId)) {
            $this->error('Plattform-ID muss eine UUID sein.');

            return self::INVALID;
        }
        $taken = $model->newQuery()->where('platform_id', strtolower((string) $platformId))->whereKeyNot($model->getKey())->exists();
        if ($taken) {
            $this->error('Diese Plattform-ID ist schon zugeordnet.');

            return self::FAILURE;
        }
        $model->forceFill(['platform_id' => strtolower((string) $platformId)])->save();
        $this->info('Zugeordnet.');

        return self::SUCCESS;
    }
}
