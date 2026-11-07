<?php

namespace App\Console\Commands;

use App\Glossary\SyncClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Sync wie ein Abnehmer prüfen (#29): Referenzclient gegen einen laufenden Server (lokal oder glossary.eduksx.ch),
 * Stand in einer Datei, danach Vergleich mit dem Vollstand. Vorlage für den Adapter im Intranet.
 */
class SyncCheck extends Command
{
    protected $signature = 'glossary:sync-check
        {url=http://127.0.0.1:8000 : Adresse des Glossary}
        {--state=storage/app/sync-check.json : Datei für den lokalen Stand des Clients}
        {--neu : Stand verwerfen und mit dem Vollstand beginnen}';

    protected $description = 'Sync über die API v1 wie ein Abnehmer prüfen (Vollstand, dann Change-Feed)';

    public function handle(): int
    {
        $url = rtrim((string) $this->argument('url'), '/');
        $path = base_path((string) $this->option('state'));

        $state = null;
        if (! $this->option('neu') && is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            $state = is_array($decoded) ? $decoded : null;
        }

        $client = new SyncClient(function (string $p) use ($url): array {
            $json = Http::acceptJson()->timeout(30)->get($url.$p)->throw()->json();

            return is_array($json) ? $json : [];
        }, $state);

        $result = $client->sync();
        $this->line(($result['art'] === 'vollstand' ? 'Vollstand geholt' : 'Änderungen geholt')
            ." – veröffentlicht {$result['published']}, zurückgezogen {$result['unpublished']},"
            ." zusammengeführt {$result['merged']}, Cursor {$result['cursor']}.");

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode($client->state(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $diff = $client->compare();
        if ($diff !== []) {
            $this->error(count($diff).' Abweichungen zum Vollstand:');
            foreach (array_slice($diff, 0, 50) as $line) {
                $this->line('  '.$line);
            }

            return self::FAILURE;
        }
        $this->info('Stand identisch ('.count($client->state()['terms']).' Begriffe).');

        return self::SUCCESS;
    }
}
