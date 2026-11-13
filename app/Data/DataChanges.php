<?php

namespace App\Data;

use App\Glossary\Journal;
use App\Models\User;
use EduKSX\Core\Http\Middleware\AssignRequestId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Änderungshistorie (A7 DB-Standard, #286) für Tabellen ohne eigenes Revisionssystem: wer, wann, was – in derselben
 * Transaktion wie die Änderung. Werte nur nach Datenklasse (B2, #276): personenbezogen und geheim nur «changed».
 * Begriffe (mit Übersetzungen, Aliasen, Beziehungen) haben Revisionen und stehen nicht hier.
 */
final class DataChanges
{
    /** Spalten, die nie als Änderung zählen. */
    private const IGNORED = ['updated_at', 'lock_version'];

    private static ?string $transaction = null;

    /** Eintrag für ein Eloquent-Modell (Trait RecordsDataChanges). */
    public static function record(Model $model, string $action): void
    {
        $table = $model->getTable();
        $changes = [];
        $trash = ['deleted_at', 'deleted_by_id'];
        if ($action === 'created') {
            foreach ($model->getAttributes() as $column => $value) {
                if (! in_array($column, self::IGNORED, true) && $value !== null) {
                    $changes[$column] = ['old' => null, 'new' => $value];
                }
            }
        } elseif ($action === 'updated') {
            foreach (array_keys($model->getChanges()) as $column) {
                if (! in_array($column, self::IGNORED, true)) {
                    $changes[$column] = ['old' => $model->getPrevious()[$column] ?? null, 'new' => $model->getAttribute($column)];
                }
            }
            // Wiederherstellen aus dem Papierkorb schreibt «restored» (eigenes Ereignis)
            if ($changes === [] || array_diff(array_keys($changes), $trash) === []) {
                return;
            }
        } elseif ($action === 'deleted' && method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
            // in den Papierkorb
            foreach ($trash as $column) {
                $changes[$column] = ['old' => null, 'new' => $model->getAttribute($column)];
            }
        }
        $organization = $model->getAttribute('organization_id');
        if ($table === 'organizations') {
            $organization = $model->getKey();
        }
        // Kontolöschung durch die Person selbst: kein Bezug auf das gelöschte Konto zurücklassen
        $self = $model instanceof User && $action === 'deleted' && auth()->id() === $model->getKey();
        self::write($table, (string) $model->getKey(), $action, DataClasses::filterChanges($table, $changes), is_string($organization) ? $organization : null, $self);
    }

    /**
     * Eintrag ohne Modell, z. B. Sammeleintrag bei Schreibzugriffen am Modell vorbei (Anzahl statt Einzelwerte).
     *
     * @param  array<string, mixed>  $changes  bereits nach Datenklasse gefiltert
     */
    public static function write(string $table, string $id, string $action, array $changes, ?string $organizationId = null, bool $anonymous = false): void
    {
        $user = auth()->user();
        $user = $user instanceof User && ! $anonymous ? $user : null;
        DB::table('data_changes')->insert([
            'event_id' => (string) Str::uuid7(),
            'subject_type' => $table,
            'subject_id' => $id,
            'organization_id' => $organizationId,
            'action' => $action,
            'changes' => $changes === [] ? null : json_encode($changes, JSON_UNESCAPED_UNICODE),
            'actor_type' => Journal::actorType($user),
            'actor_id' => $user?->id,
            'actor_label' => Journal::actorLabel($user),
            'source' => self::source(),
            'transaction_id' => self::transaction(),
            'request_id' => app()->runningInConsole() ? null : AssignRequestId::forRequest(request()),
            'schema_version' => mb_substr((string) config('glossary.version'), 0, 20),
            'occurred_at' => now()->utc()->toDateTimeString(),
        ]);
    }

    private static function source(): string
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return 'job';
        }

        return request()->is('api/*') ? 'api' : 'web';
    }

    /** Gemeinsame Kennung aller Einträge einer Datenbank-Transaktion; ausserhalb je Eintrag neu. */
    private static function transaction(): string
    {
        if (DB::transactionLevel() === 0) {
            return (string) Str::uuid7();
        }
        $id = self::$transaction;
        if ($id === null) {
            $id = self::$transaction = (string) Str::uuid7();
            DB::afterCommit(function (): void {
                self::$transaction = null;
            });
        }

        return $id;
    }

    /** Kontolöschung: Bezug und Bezeichnung der Person in der Historie entfernen (B2: Bezug pseudonymisieren). */
    public static function forget(User $user): void
    {
        DB::table('data_changes')->where('actor_id', $user->id)->update(['actor_id' => null, 'actor_label' => Journal::DELETED_LABEL]);
    }
}
