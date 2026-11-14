<?php

namespace App\Data;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Alle Daten einer Person (DB-Standard B8, #312): findet über den Personenbezug (Spalten, die auf ein Konto verweisen)
 * und die Datenklassen (B2) alle Zeilen in allen Tabellen – auch Historie, Vorschläge und Protokolle – und gibt sie
 * maschinenlesbar aus (Felder englisch, Bezeichnungen deutsch).
 *
 * - Geheime Spalten (Passwort, Tokens, zweiter Faktor) erscheinen nie.
 * - Verweise auf **andere** Personen und deren Bezeichnungen werden ersetzt («andere Person»), damit der Export nichts
 *   von anderen enthält.
 */
final class PersonalData
{
    public const FORMAT = 'eduksx.glossary.personal-data/1';

    public const OTHER = 'andere Person';

    /** Spaltennamen, die auf ein Konto verweisen. */
    private const REFERENCE = '/^(user_id|actor_id|author_id|target_user_id|responsible_user_id)$|_by_id$/';

    /** Bezeichnung einer Person neben ihrem Verweis (gleiche Zeile). */
    private const LABELS = [
        'actor_label' => 'actor_id',
        'author_label' => 'author_id',
        'user_label' => 'imported_by_id',
    ];

    /**
     * Spalten je Tabelle, die auf ein Konto verweisen.
     *
     * @return array<string, list<string>>
     */
    public static function references(): array
    {
        $out = [];
        foreach (DataClasses::MAP as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                if ($table !== 'users' && preg_match(self::REFERENCE, $column) === 1) {
                    $out[$table][] = $column;
                }
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        $tables = [];
        $tables['users'] = $this->rows('users', DB::table('users')->where('id', $user->id)->get()->all(), $user);
        $tables['organization_invitations'] = $this->rows('organization_invitations',
            DB::table('organization_invitations')->whereRaw('lower(email) = ?', [mb_strtolower($user->email)])->get()->all(), $user);
        foreach (self::references() as $table => $columns) {
            $rows = DB::table($table)->where(function ($q) use ($columns, $user): void {
                foreach ($columns as $column) {
                    $q->orWhere($column, $user->id);
                }
            })->get()->all();
            $tables[$table] = $this->rows($table, $rows, $user, $columns);
        }
        $tables = array_filter($tables, fn (array $t): bool => $t['rows'] !== []);
        ksort($tables);

        return [
            'format' => self::FORMAT,
            'created_at' => now()->utc()->toIso8601ZuluString(),
            'module' => 'glossary',
            'subject' => ['id' => $user->id, 'email' => $user->email],
            'note' => 'Alle Daten des Kontos im Glossary (Auskunft und Datenherausgabe). Verweise auf andere Personen sind ersetzt.',
            'tables' => $tables,
        ];
    }

    /**
     * @param  array<int, object>  $rows
     * @param  list<string>  $references
     * @return array{purpose: ?string, labels: array<string, string>, rows: list<array<string, mixed>>}
     */
    private function rows(string $table, array $rows, User $user, array $references = []): array
    {
        $out = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ((array) $row as $column => $value) {
                $class = DataClasses::of($table, $column);
                if ($class === DataClass::Secret) {
                    continue;
                }
                if ($value !== null && in_array($column, $references, true) && $value !== $user->id) {
                    $value = self::OTHER;
                }
                $values[$column] = $value;
            }
            foreach (self::LABELS as $label => $reference) {
                if (array_key_exists($label, $values) && ($values[$reference] ?? $user->id) === self::OTHER) {
                    $values[$label] = self::OTHER;
                }
            }
            $out[] = $values;
        }
        $labels = [];
        foreach (array_keys($out[0] ?? []) as $column) {
            $labels[$column] = DataClasses::of($table, $column)->label();
        }
        $purpose = Purposes::of($table);

        return [
            'purpose' => $purpose !== null ? Purposes::MAP[$purpose]['purpose'] : null,
            'labels' => $labels,
            'rows' => $out,
        ];
    }
}
