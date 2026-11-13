<?php

namespace App\Console\Commands;

use App\Access\Audit;
use App\Access\Permission;
use App\Mail\InactivityNotice;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\Organization;
use App\Models\Proposal;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use App\Proposals\ProposalService;
use App\Search\SearchMisses;
use App\Support\MailSetup;
use App\Support\Settings;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Löschfristen anwenden (docs/LOESCHKONZEPT.md, #41, #42). Läuft täglich über den Dienst «scheduler».
 *
 * - Unbestätigte Registrierungen: nach 7 Tagen löschen.
 * - Inaktive Community-Konten (nur Grundrechte, z. B. Gast oder Benutzer): nach 23 Monaten ohne Anmeldung Hinweis per Mail, 30 Tage danach löschen.
 *   Ohne Mailversand kein Hinweis und darum auch keine Löschung.
 * - Audit-Protokoll: Einträge älter als die Aufbewahrungsfrist (vorläufig 5 Jahre) löschen.
 * - Vorschläge (#113): 24 Monate nach Abschluss ohne Personenbezug («Community»).
 */
class ApplyRetention extends Command
{
    protected $signature = 'glossary:loeschfristen {--trocken : Nur anzeigen, nichts löschen oder senden}';

    protected $description = 'Löschfristen anwenden (Registrierungen, inaktive Konten, Protokoll)';

    public function handle(): int
    {
        $dry = (bool) $this->option('trocken');
        // Fristen aus den Einstellungen (#106), Vorgabe aus config/glossary.php
        $months = Settings::int('retention.inactive_months');
        $noticeDays = Settings::int('retention.notice_days');
        $auditYears = Settings::int('retention.audit_years');
        $proposalMonths = Settings::int('retention.proposal_months');

        // 1. Unbestätigte Registrierungen
        $pending = User::query()->where('status', 'pending')->where('created_at', '<', now()->subDays(7))->get();
        foreach ($pending as $user) {
            $this->deleteUser($user, 'Registrierung nicht bestätigt (7 Tage)', $dry);
        }

        // 2. Inaktive Community-Konten
        $notified = 0;
        $deleted = 0;
        if (MailSetup::configured()) {
            $inactiveSince = now()->subMonths($months)->addDays($noticeDays);
            foreach ($this->inactiveCommunity($inactiveSince) as $user) {
                if ($user->inactivity_notice_sent_at === null) {
                    if (! $dry) {
                        $this->notify($user, now()->addDays($noticeDays)->toDateString());
                    }
                    $notified++;
                } elseif ($user->inactivity_notice_sent_at->lt(now()->subDays($noticeDays))) {
                    $this->deleteUser($user, "Inaktiv seit {$months} Monaten (Hinweis gesendet)", $dry);
                    $deleted++;
                }
            }
        }

        // 3. Audit-Protokoll (Abfrage statt Model: Einträge sind sonst unveränderlich)
        $auditQuery = DB::table('user_audit')->where('created_at', '<', now()->subYears($auditYears));
        $audit = $dry ? $auditQuery->count() : $auditQuery->delete();
        // Änderungsprotokoll (#286): gleiche Frist wie das Audit-Protokoll
        $changesQuery = DB::table('data_changes')->where('occurred_at', '<', now()->subYears($auditYears));
        $audit += $dry ? $changesQuery->count() : $changesQuery->delete();

        // 4. Vorschläge (#113, DEC-024): 24 Monate nach Abschluss ohne Personenbezug
        $proposals = $dry
            ? Proposal::query()->whereNotNull('resolved_at')->where('resolved_at', '<', now()->subMonths($proposalMonths))
                ->whereNotNull('user_id')->count()
            : app(ProposalService::class)->anonymizeResolvedBefore(now()->subMonths($proposalMonths));

        // 5. Import-Protokoll (#64): 12 Monate
        $importQuery = ImportBatch::query()->where('created_at', '<', now()->subMonths(ImportBatch::RETENTION_MONTHS));
        $imports = $dry ? $importQuery->count() : $importQuery->delete();

        // 6. Suchen ohne Treffer (X4, #285): 90 Tage nach dem letzten Auftreten
        $misses = $dry
            ? DB::table('search_misses')->where('last_seen_at', '<', now()->subDays(SearchMisses::RETENTION_DAYS))->count()
            : SearchMisses::prune();

        // 7. Papierkorb (#286): nach der Frist endgültig löschen
        $trashed = 0;
        foreach ([Category::class, Source::class, Organization::class] as $model) {
            $old = $model::onlyTrashed()->where('deleted_at', '<', now()->subDays($model::trashDays()))->get();
            $trashed += $old->count();
            if (! $dry) {
                foreach ($old as $item) {
                    $item->forceDelete();
                }
            }
        }

        $prefix = $dry ? 'Würde: ' : '';
        $this->info("{$prefix}{$pending->count()} unbestätigte Registrierungen gelöscht, {$notified} Hinweise gesendet, "
            ."{$deleted} inaktive Konten gelöscht, {$audit} Protokolleinträge und {$imports} Import-Protokolle entfernt, "
            ."{$proposals} Vorschläge ohne Personenbezug, {$misses} Suchen ohne Treffer, {$trashed} Einträge aus dem Papierkorb.");

        return self::SUCCESS;
    }

    /**
     * Community-Konten (aktiv, keine Rolle mit Recht der Administration und nicht Developer, #110), deren letzte
     * Anmeldung (sonst Anlage) vor $since liegt.
     *
     * @return iterable<User>
     */
    private function inactiveCommunity(DateTimeInterface $since): iterable
    {
        return User::query()
            ->where('status', 'active')
            ->whereDoesntHave('roles', fn ($q) => $q->where(fn ($q) => $q->where('roles.key', Role::DEVELOPER)
                ->orWhereIn('roles.id', DB::table('role_permissions')->whereIn('permission', Permission::privilegedArea())->select('role_id'))))
            ->where(function ($q) use ($since): void {
                $q->where('last_login_on', '<', $since->format('Y-m-d'))
                    ->orWhere(fn ($q) => $q->whereNull('last_login_on')->where('created_at', '<', $since));
            })
            ->get();
    }

    private function notify(User $user, string $deleteOn): void
    {
        try {
            Mail::to($user->email)->send(new InactivityNotice(date('d.m.Y', (int) strtotime($deleteOn))));
            $user->forceFill(['inactivity_notice_sent_at' => now()])->save();
        } catch (Throwable $e) {
            Log::warning('Hinweis vor Löschung fehlgeschlagen', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }
    }

    private function deleteUser(User $user, string $reason, bool $dry): void
    {
        if ($dry) {
            return;
        }
        Audit::log(null, $user, 'account.deleted', ['reason' => $reason], 'ok', 'Löschfrist');
        DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        $user->delete();
    }
}
