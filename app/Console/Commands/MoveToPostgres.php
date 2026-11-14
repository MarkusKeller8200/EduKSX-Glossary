<?php

namespace App\Console\Commands;

use App\Database\TableLoader;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Umzug SQLite → PostgreSQL (#191, #333, DB-Standard E6): kopiert alle Tabellen aus der SQLite-Datenbank in das Schema
 * `glossary` einer frisch migrierten PostgreSQL-Datenbank – ohne Datenverlust (Zählung vorher = nachher).
 *
 * Ablauf (docs/KONZEPT-DEPLOYMENT.md «Umzug nach PostgreSQL»):
 *  1. Sicherung der SQLite-Datei, Anwendung anhalten.
 *  2. PostgreSQL-Verbindung als «pgsql» konfigurieren, `php artisan migrate --database=pgsql`.
 *  3. `php artisan glossary:umzug-postgres --quelle=sqlite --ziel=pgsql` (Probelauf mit --trocken).
 *  4. DB_CONNECTION=pgsql setzen, Anwendung starten.
 *
 * Reihenfolge nach Fremdschlüsseln; gegenseitige Verweise (z. B. terms ↔ term_revisions) und Verweise auf die eigene
 * Tabelle werden zuerst leer gelassen und danach gesetzt. Zähler (Sequenzen) folgen dem höchsten Wert.
 */
class MoveToPostgres extends Command
{
    protected $signature = 'glossary:umzug-postgres
        {--quelle=sqlite : Verbindung der SQLite-Datenbank}
        {--ziel=pgsql : Verbindung der PostgreSQL-Datenbank (migriert)}
        {--leeren : Daten im Ziel vorher löschen (z. B. Grunddaten aus den Migrationen)}
        {--trocken : Nur prüfen und zählen, nichts schreiben}';

    protected $description = 'SQLite-Datenbank nach PostgreSQL (Schema glossary) übernehmen (#191)';

    public function handle(): int
    {
        $source = DB::connection((string) $this->option('quelle'));
        $target = DB::connection((string) $this->option('ziel'));
        if ($source->getDriverName() !== 'sqlite' || $target->getDriverName() !== 'pgsql') {
            $this->error('Quelle muss SQLite, Ziel PostgreSQL sein.');

            return self::INVALID;
        }

        $tables = array_values(array_filter(
            array_column($source->getSchemaBuilder()->getTables(), 'name'),
            fn (string $t): bool => ! str_starts_with($t, 'sqlite_') && $t !== 'migrations',
        ));
        $this->assertSameMigrations($source, $target);
        $missing = array_diff($tables, array_column($target->getSchemaBuilder()->getTables(), 'name'));
        if ($missing !== []) {
            throw new RuntimeException('Im Ziel fehlen Tabellen: '.implode(', ', $missing));
        }

        $loader = new TableLoader($target, $source);
        [$order] = $loader->order($tables);
        $counts = [];
        foreach ($order as $table) {
            $counts[$table] = $source->table($table)->count();
        }
        if ($this->option('trocken')) {
            $this->table(['Tabelle', 'Zeilen'], array_map(fn ($t) => [$t, $counts[$t]], $order));

            return self::SUCCESS;
        }

        $target->transaction(function () use ($source, $target, $order, $loader): void {
            if ($this->option('leeren')) {
                $target->statement('truncate '.implode(', ', array_map(fn ($t) => '"'.$t.'"', $order)).' restart identity cascade');
            }
            foreach ($order as $table) {
                if ($target->table($table)->exists()) {
                    throw new RuntimeException("Ziel ist nicht leer: {$table} (Option --leeren).");
                }
            }
            // Zeilen seitenweise, sortiert nach dem ganzen Schlüssel (auch zusammengesetzt, z. B. term_categories)
            /**
             * @param  list<string>  $key
             * @param  list<string>|null  $columns
             * @return iterable<object>
             */
            $loader->load($order, function (string $table, array $key, ?array $columns) use ($source): iterable {
                $query = $source->table($table)->select($columns ?? ['*']);
                foreach ($key as $column) {
                    $query->orderBy($column);
                }

                return $query->lazy(TableLoader::CHUNK);
            });
        });

        $rows = [];
        $ok = true;
        foreach ($order as $table) {
            $after = $target->table($table)->count();
            $ok = $ok && $after === $counts[$table];
            $rows[] = [$table, $counts[$table], $after];
        }
        $this->table(['Tabelle', 'SQLite', 'PostgreSQL'], $rows);
        if (! $ok) {
            $this->error('Zählung weicht ab.');

            return self::FAILURE;
        }
        $this->info('Übernommen: '.count($order).' Tabellen, Zählung gleich.');

        return self::SUCCESS;
    }

    private function assertSameMigrations(Connection $source, Connection $target): void
    {
        $a = $source->table('migrations')->orderBy('migration')->pluck('migration')->all();
        $b = $target->table('migrations')->orderBy('migration')->pluck('migration')->all();
        if ($a !== $b) {
            throw new RuntimeException('Quelle und Ziel haben nicht dieselben Migrationen – beide auf denselben Stand bringen.');
        }
    }
}
