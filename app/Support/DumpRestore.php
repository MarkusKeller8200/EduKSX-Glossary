<?php

namespace App\Support;

use App\Data\DataClasses;
use App\Database\TableLoader;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Database\Connection;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Dump in eine Datenbank einspielen (#313, #353, #355) – SQLite oder PostgreSQL, gleich welche Datenbank den Dump
 * geschrieben hat. Alles in einer Transaktion: Bricht ein Schritt ab, bleibt die Datenbank unverändert.
 *
 * - **vollständig:** ersetzt alle Tabellen der Anwendung; die Tabellen des Frameworks (Sitzungen, Cache, Warteschlange,
 *   Links zum Zurücksetzen des Passworts) werden geleert und nicht übernommen – alle melden sich neu an, und es werden
 *   keine Mails aus der Warteschlange der Quelle verschickt
 * - **anonymisiert:** ersetzt nur die Tabellen der Anwendung (Tabellen des Frameworks bleiben)
 * - **Konten behalten** (Sicherung auf dev, #355): Konten mit Passwort, zweitem Faktor und Anmeldungen über Microsoft,
 *   Rollen und Rechte sowie die Sitzungen bleiben, wie sie sind; alles andere kommt aus dem Dump. Verweise im Dump auf
 *   Konten, die es hier nicht gibt, werden geleert, bzw. die Zeile entfällt (z. B. die Mitgliedschaft eines Kontos, das
 *   inzwischen gelöscht ist).
 *
 * Mit APP_KEY verschlüsselte Werte (zweiter Faktor, Passwort des Mailversands) werden mit dem Schlüssel der Quelle
 * entschlüsselt und mit dem eigenen neu verschlüsselt; ohne passenden Schlüssel bricht das Einspielen vorher ab.
 */
final class DumpRestore
{
    /** Tabellen, die bei «Konten behalten» unverändert bleiben */
    public const ACCOUNT_TABLES = ['users', 'user_identities', 'user_roles', 'roles', 'role_permissions', 'sessions', 'password_reset_tokens'];

    /** Spalten mit Werten, die mit APP_KEY verschlüsselt sind */
    private const ENCRYPTED = ['users' => ['two_factor_secret', 'two_factor_recovery_codes']];

    private ?Encrypter $source = null;

    /** @var array<string, int> Zeilen je Tabelle, die wegen fehlender Konten entfallen sind */
    private array $dropped = [];

    /** @param  list<string>  $keep  Tabellen, die unverändert bleiben */
    private function __construct(private readonly DumpArchive $dump, private readonly Connection $db, private readonly array $keep = []) {}

    /**
     * @param  string|null  $sourceKey  APP_KEY der Quelle (nur nötig, wenn er sich vom eigenen unterscheidet)
     * @return array<string, int> Tabelle → eingespielte Zeilen
     *
     * @throws RuntimeException
     */
    public static function run(DumpArchive $dump, Connection $db, ?string $sourceKey = null): array
    {
        $restore = new self($dump, $db);
        $tables = $restore->check($sourceKey);

        return $restore->load($tables);
    }

    /**
     * Einspielen und dabei Konten, Rollen und Sitzungen behalten (#355).
     *
     * @return array{tables: array<string, int>, dropped: array<string, int>} eingespielte und entfallene Zeilen je Tabelle
     *
     * @throws RuntimeException
     */
    public static function runKeepingAccounts(DumpArchive $dump, Connection $db): array
    {
        $restore = new self($dump, $db, self::ACCOUNT_TABLES);
        $tables = $restore->check(null);

        return ['tables' => $restore->load($tables), 'dropped' => $restore->dropped];
    }

    /**
     * Nur prüfen, nichts ändern (Probelauf): Migrationen, Tabellen und Schlüssel.
     *
     * @return list<string> Tabellen, die ersetzt würden
     *
     * @throws RuntimeException
     */
    public static function dryRun(DumpArchive $dump, Connection $db, ?string $sourceKey = null): array
    {
        return (new self($dump, $db))->check($sourceKey);
    }

    /** Wurden verschlüsselte Werte im Dump mit einem anderen APP_KEY verschlüsselt? */
    public static function keyDiffers(DumpArchive $dump, Connection $db): bool
    {
        $restore = new self($dump, $db);
        $sample = $dump->anonymized() ? null : $restore->encryptedSample();

        return $sample !== null && ! $restore->decrypts(app(StringEncrypter::class), $sample);
    }

    /** @return list<string> */
    private function check(?string $sourceKey): array
    {
        $this->checkMigrations();
        $tables = $this->tables();
        $this->checkKey($sourceKey);

        return $tables;
    }

    private function checkMigrations(): void
    {
        $local = $this->db->table('migrations')->orderBy('migration')->pluck('migration')->all();
        if ($local !== $this->dump->migrations()) {
            throw new RuntimeException('Migrationen passen nicht zum Dump – Quelle und Ziel müssen dieselbe Version haben (zuerst gleiche Version installieren bzw. migrieren).');
        }
    }

    /** @return list<string> lokale Tabellen ohne migrations */
    private function localTables(): array
    {
        return array_values(array_filter(
            array_column($this->db->getSchemaBuilder()->getTables(), 'name'),
            fn (string $t): bool => ! str_starts_with($t, 'sqlite_') && $t !== 'migrations',
        ));
    }

    /** @return list<string> Tabellen der Anwendung, die ersetzt werden */
    private function tables(): array
    {
        $inDump = array_values(array_diff(array_keys($this->dump->tables()), DataClasses::FRAMEWORK));
        $local = $this->localTables();
        $unknown = array_diff($inDump, $local);
        if ($unknown !== []) {
            throw new RuntimeException('Tabellen im Dump, die es hier nicht gibt: '.implode(', ', $unknown));
        }
        if (! $this->dump->anonymized()) {
            $missing = array_diff($local, $inDump, DataClasses::FRAMEWORK, $this->keep);
            if ($missing !== []) {
                throw new RuntimeException('Tabellen fehlen im Dump: '.implode(', ', $missing));
            }
        }

        return array_values(array_diff($inDump, $this->keep));
    }

    /** Prüft an einem verschlüsselten Wert, ob der eigene Schlüssel oder der der Quelle passt – vor jeder Änderung. */
    private function checkKey(?string $sourceKey): void
    {
        if ($this->dump->anonymized()) {
            return;   // anonymisiert: Geheimnisse sind ersetzt (#313), nichts zu entschlüsseln
        }
        $sample = $this->encryptedSample();
        if ($sample === null || $this->decrypts(app(StringEncrypter::class), $sample)) {
            return;   // nichts verschlüsselt oder gleicher Schlüssel
        }
        if ($sourceKey === null || $sourceKey === '') {
            throw new RuntimeException('Der Dump wurde mit einem anderen APP_KEY verschlüsselt (zweiter Faktor, Mailversand) – Schlüssel der Quelle mit --schluessel-quelle angeben.');
        }
        $key = str_starts_with($sourceKey, 'base64:') ? base64_decode(substr($sourceKey, 7)) : $sourceKey;
        try {
            $source = new Encrypter((string) $key, (string) config('app.cipher'));
        } catch (RuntimeException $e) {
            throw new RuntimeException('Schlüssel der Quelle ist ungültig: '.$e->getMessage(), 0, $e);
        }
        if (! $this->decrypts($source, $sample)) {
            throw new RuntimeException('Schlüssel der Quelle passt nicht zum Dump.');
        }
        $this->source = $source;
    }

    /** Ein verschlüsselter Wert aus den Tabellen, die eingespielt werden */
    private function encryptedSample(): ?string
    {
        foreach (self::ENCRYPTED as $table => $columns) {
            if (in_array($table, $this->keep, true)) {
                continue;
            }
            foreach ($this->dump->rows($table) as $row) {
                foreach ($columns as $column) {
                    if (is_string($row[$column] ?? null) && $row[$column] !== '') {
                        return $row[$column];
                    }
                }
            }
        }
        foreach ($this->dump->rows('settings') as $row) {
            if (($row['key'] ?? null) === MailSettings::KEY) {
                $password = json_decode((string) $row['value'], true)['password'] ?? '';

                return is_string($password) && $password !== '' ? $password : null;
            }
        }

        return null;
    }

    private function decrypts(StringEncrypter $encrypter, string $value): bool
    {
        try {
            $encrypter->decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function load(array $tables): array
    {
        if ($this->keep !== []) {
            $clear = array_values(array_diff($this->localTables(), $this->keep));
        } elseif (! $this->dump->anonymized()) {
            $clear = $this->localTables();
        } else {
            $clear = $tables;
        }
        $refs = $this->referencesToKept($tables);
        $counts = [];

        $this->db->transaction(function () use ($tables, $clear, $refs, &$counts): void {
            if ($this->db->getDriverName() === 'sqlite') {
                $this->db->statement('PRAGMA defer_foreign_keys = ON');   // Verweise erst beim Abschluss prüfen
                foreach ($clear as $table) {
                    $this->db->table($table)->delete();
                }
            } else {
                // offene, aufgeschobene Prüfungen zuerst ausführen – sonst lässt PostgreSQL kein TRUNCATE zu
                $this->db->statement('set constraints all immediate');
                // mit behaltenen Tabellen ohne cascade: ein Verweis von dort bricht ab, statt sie mitzuleeren
                $this->db->statement('truncate '.implode(', ', array_map(fn (string $t): string => '"'.$t.'"', $clear)).' restart identity'.($this->keep === [] ? ' cascade' : ''));
            }

            // Zeilen, die entfallen, nur beim ersten Lesen zählen (Tabellen in einem Kreis liest der Lader zweimal)
            $this->dropped = [];
            $seen = [];
            /** @return list<array<string, mixed>> */
            $rows = function (string $table) use ($refs, &$seen): array {
                $first = ! isset($seen[$table]);
                $seen[$table] = true;
                $out = [];
                foreach ($this->dump->rows($table) as $row) {
                    $row = $this->keepReferences($table, $row, $refs[$table] ?? [], $first);
                    if ($row !== null) {
                        $out[] = $this->reencrypt($table, $row);
                    }
                }

                return $out;
            };
            (new TableLoader($this->db))->load($tables, $rows);

            foreach ($tables as $table) {
                $expected = $this->dump->tables()[$table] - ($this->dropped[$table] ?? 0);
                if ($this->db->table($table)->count() !== $expected) {
                    throw new RuntimeException("Zählung weicht ab: {$table} (erwartet {$expected})");
                }
                $counts[$table] = $expected;
            }
        });

        return $counts;
    }

    /**
     * Verweise der eingespielten Tabellen auf behaltene Tabellen: Spalte → [Ziel-Spalte, vorhandene Werte, nullbar].
     *
     * @param  list<string>  $tables
     * @return array<string, array<string, array{0: array<string, true>, 1: bool}>>
     */
    private function referencesToKept(array $tables): array
    {
        if ($this->keep === []) {
            return [];
        }
        $existing = [];
        $refs = [];
        foreach ($tables as $table) {
            $nullable = array_column($this->db->getSchemaBuilder()->getColumns($table), 'nullable', 'name');
            foreach ($this->db->getSchemaBuilder()->getForeignKeys($table) as $fk) {
                if (! in_array($fk['foreign_table'], $this->keep, true) || count($fk['columns']) !== 1) {
                    continue;
                }
                $target = $fk['foreign_table'].'.'.$fk['foreign_columns'][0];
                $existing[$target] ??= array_fill_keys(
                    array_map('strval', $this->db->table($fk['foreign_table'])->pluck($fk['foreign_columns'][0])->all()),
                    true,
                );
                $refs[$table][$fk['columns'][0]] = [$existing[$target], (bool) ($nullable[$fk['columns'][0]] ?? false)];
            }
        }

        return $refs;
    }

    /**
     * Verweis auf ein Konto, das es hier nicht gibt: leeren, wenn erlaubt, sonst entfällt die Zeile (null).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, array{0: array<string, true>, 1: bool}>  $refs
     * @return array<string, mixed>|null
     */
    private function keepReferences(string $table, array $row, array $refs, bool $count): ?array
    {
        foreach ($refs as $column => [$existing, $nullable]) {
            $value = $row[$column] ?? null;
            if ($value === null || isset($existing[(string) $value])) {
                continue;
            }
            if (! $nullable) {
                if ($count) {
                    $this->dropped[$table] = ($this->dropped[$table] ?? 0) + 1;
                }

                return null;
            }
            $row[$column] = null;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function reencrypt(string $table, array $row): array
    {
        if ($this->source === null) {
            return $row;
        }
        foreach (self::ENCRYPTED[$table] ?? [] as $column) {
            if (is_string($row[$column] ?? null) && $row[$column] !== '') {
                $row[$column] = Crypt::encryptString($this->source->decryptString($row[$column]));
            }
        }
        if ($table === 'settings' && ($row['key'] ?? null) === MailSettings::KEY) {
            $value = json_decode((string) $row['value'], true);
            if (is_array($value) && is_string($value['password'] ?? null) && $value['password'] !== '') {
                $value['password'] = Crypt::encryptString($this->source->decryptString($value['password']));
                $row['value'] = json_encode($value, JSON_THROW_ON_ERROR);
            }
        }

        return $row;
    }
}
