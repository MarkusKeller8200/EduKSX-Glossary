<?php

namespace App\Proposals;

use App\Glossary\GlossaryService;
use App\Glossary\Journal;
use App\Glossary\OfficialFieldLocked;
use App\Models\Category;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\ServiceAccount;
use App\Models\Term;
use App\Models\TermRevision;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Einzige Stelle für Vorschläge (#113, DEC-022, DEC-024): Einreichen (Konto mit «propose» oder Servicekonto mit
 * Scope), Rückfrage und Antwort, Annehmen mit feldweiser Konfliktprüfung, Ablehnen mit Begründung, Zurückziehen.
 *
 * - Eine Korrektur nennt die veröffentlichte Revision als Basis; gespeichert werden nur die geänderten Felder.
 * - Annehmen (Recht «moderate») schreibt über GlossaryService::save eine neue Revision als **Entwurf** (Kontext
 *   «vorschlag»); veröffentlicht wird getrennt mit «publish». Felder mit Konflikt (seit der Basis geändert) werden
 *   nie still überschrieben: die Moderation wählt die Felder ausdrücklich.
 * - Nie öffentlich. Personenbezug 24 Monate nach Abschluss bzw. bei Kontolöschung entfernt («Community»).
 */
final class ProposalService
{
    /** Felder, die vorgeschlagen werden können (Positivliste) → Bezeichnung. */
    public const FIELDS = [
        'official_abbreviation' => 'Abkürzung',
        'official_name' => 'Bezeichnung',
        'short_description' => 'Kurzbeschreibung',
        'description' => 'Beschreibung',
        'url' => 'URL',
        'alternative_aliases' => 'Alternative Schreibweisen',
        'categories' => 'Kategorien',
    ];

    /** Listenfelder. */
    public const LIST_FIELDS = ['alternative_aliases', 'categories'];

    /** Aufbewahrung mit Personenbezug nach Abschluss (Entscheid 08.11.2026). */
    public const RETENTION_MONTHS = 24;

    public function __construct(private readonly GlossaryService $glossary) {}

    /**
     * Vorschlag einreichen. $term null = neuer Begriff. $values: Felder aus FIELDS (Listen als Array oder «a; b»).
     *
     * @param  array<string, mixed>  $values
     *
     * @throws InvalidArgumentException bei ungültigen oder fehlenden Angaben
     */
    public function submit(?Term $term, array $values, ?string $reason, ?User $user, ?ServiceAccount $service = null): Proposal
    {
        if ($user === null && $service === null) {
            throw new InvalidArgumentException('Vorschläge brauchen ein Konto oder ein Servicekonto.');
        }
        if ($term !== null && ($term->published_revision_id === null || $term->merged_into_id !== null)) {
            throw new InvalidArgumentException('Korrekturen sind nur zu veröffentlichten Begriffen möglich.');
        }
        $values = $this->clean($values);
        $base = null;
        if ($term !== null) {
            $base = $term->publishedRevision;
            if ($base === null) {
                throw new InvalidArgumentException('Korrekturen sind nur zu veröffentlichten Begriffen möglich.');
            }
            // nur Felder, die sich vom öffentlichen Stand unterscheiden
            $values = array_filter($values, fn (mixed $v, string $f): bool => $this->norm($v) !== $this->norm($base->snapshot[$f] ?? null),
                ARRAY_FILTER_USE_BOTH);
            if ($values === []) {
                throw new InvalidArgumentException('Der Vorschlag ändert nichts am veröffentlichten Stand.');
            }
        } elseif (($values['official_abbreviation'] ?? '') === '' && ($values['official_name'] ?? '') === '') {
            throw new InvalidArgumentException('Für einen neuen Begriff Abkürzung oder Bezeichnung angeben.');
        }
        $reason = trim((string) $reason);
        if (mb_strlen($reason) > 2000) {
            throw new InvalidArgumentException('Begründung höchstens 2000 Zeichen.');
        }
        $label = $service !== null ? 'Servicekonto «'.$service->name.'»' : (string) Journal::actorLabel($user);

        return DB::transaction(function () use ($term, $values, $reason, $user, $service, $base, $label): Proposal {
            $proposal = Proposal::query()->create([
                'kind' => $term === null ? 'new' : 'change',
                'term_id' => $term?->id,
                'base_revision' => $base?->revision,
                'changes' => $values,
                'reason' => $reason === '' ? null : $reason,
                'status' => 'eingereicht',
                'user_id' => $user?->id,
                'service_account_id' => $service?->id,
                'author_label' => $label,
            ]);
            $this->event($proposal, 'eingereicht', $user, true, null, $service !== null ? ['service_account' => $service->id] : null, $label);

            return $proposal;
        });
    }

    /** Rückfrage der Moderation an die einreichende Person. */
    public function ask(Proposal $proposal, User $moderator, string $message): void
    {
        $this->requireOpen($proposal);
        $this->requireText($message, 'Bitte die Rückfrage formulieren.');
        DB::transaction(function () use ($proposal, $moderator, $message): void {
            $proposal->forceFill(['status' => 'rueckfrage'])->save();
            $this->event($proposal, 'rueckfrage', $moderator, false, $message);
        });
    }

    /** Antwort der einreichenden Person; der Vorschlag geht zurück an die Moderation. */
    public function answer(Proposal $proposal, User $author, string $message): void
    {
        $this->requireAuthor($proposal, $author);
        if ($proposal->status !== 'rueckfrage') {
            throw new InvalidArgumentException('Zu diesem Vorschlag gibt es keine offene Rückfrage.');
        }
        $this->requireText($message, 'Bitte eine Antwort eingeben.');
        DB::transaction(function () use ($proposal, $author, $message): void {
            $proposal->forceFill(['status' => 'eingereicht'])->save();
            $this->event($proposal, 'antwort', $author, true, $message);
        });
    }

    public function withdraw(Proposal $proposal, User $author): void
    {
        $this->requireAuthor($proposal, $author);
        $this->requireOpen($proposal);
        DB::transaction(function () use ($proposal, $author): void {
            $proposal->forceFill(['status' => 'zurueckgezogen', 'resolved_at' => now()])->save();
            $this->event($proposal, 'zurueckgezogen', $author, true);
        });
    }

    public function reject(Proposal $proposal, User $moderator, string $reason): void
    {
        $this->requireOpen($proposal);
        $this->requireText($reason, 'Bitte eine Begründung für die Ablehnung angeben.');
        DB::transaction(function () use ($proposal, $moderator, $reason): void {
            $proposal->forceFill(['status' => 'abgelehnt', 'resolved_at' => now()])->save();
            $this->event($proposal, 'abgelehnt', $moderator, false, $reason);
        });
    }

    /**
     * Annehmen: die gewählten Felder als neue Revision (Entwurf). Neue Begriffe werden angelegt.
     *
     * @param  list<string>  $fields  übernommene Felder (bei Konflikt ausdrücklich wählen)
     *
     * @throws InvalidArgumentException|OfficialFieldLocked
     */
    public function accept(Proposal $proposal, User $moderator, array $fields, bool $canEditOfficial, ?string $note = null): Term
    {
        $this->requireOpen($proposal);
        $data = array_intersect_key($proposal->changes, array_flip($fields));
        if ($data === []) {
            throw new InvalidArgumentException('Mindestens ein Feld übernehmen – oder den Vorschlag ablehnen.');
        }
        $term = $proposal->kind === 'change' ? $proposal->term : null;
        if ($proposal->kind === 'change' && $term === null) {
            throw new InvalidArgumentException('Den Begriff gibt es nicht mehr.');
        }
        if ($term !== null && $term->merged_into_id !== null) {
            throw new InvalidArgumentException('Der Begriff wurde zusammengeführt; Vorschlag bitte ablehnen oder neu erfassen.');
        }

        return DB::transaction(function () use ($proposal, $moderator, $data, $term, $canEditOfficial, $note, $fields): Term {
            $context = ['via' => 'vorschlag', 'proposal_id' => $proposal->id, 'proposed_by' => $proposal->author_label];
            $result = $this->glossary->save($term, $data, $moderator, $canEditOfficial, null, 'updated', $context);
            $proposal->forceFill([
                'status' => 'angenommen', 'resolved_at' => now(),
                'result_term_id' => $result->id, 'result_revision' => $result->revision,
            ])->save();
            $skipped = array_values(array_diff(array_keys($proposal->changes), $fields));
            $this->event($proposal, 'angenommen', $moderator, false, $note, [
                'revision' => $result->revision, 'fields' => array_keys($data), 'skipped' => $skipped,
            ]);

            return $result;
        });
    }

    /**
     * Feldweiser Vergleich für die Moderation: Basis (veröffentlicht beim Einreichen), aktueller Arbeitsstand und
     * Vorschlag. Konflikt = das Feld hat sich seit der Basis geändert.
     *
     * @return list<array{feld: string, label: string, basis: mixed, aktuell: mixed, vorschlag: mixed, konflikt: bool, erledigt: bool}>
     */
    public function compare(Proposal $proposal): array
    {
        $base = [];
        $current = [];
        if ($proposal->kind === 'change' && $proposal->term !== null) {
            $base = TermRevision::query()->where('term_id', $proposal->term_id)->where('revision', $proposal->base_revision)
                ->value('snapshot') ?? [];
            $base = is_array($base) ? $base : (array) json_decode((string) $base, true);
            $current = $this->glossary->snapshot($proposal->term);
        }
        $rows = [];
        foreach ($proposal->changes as $field => $value) {
            $b = $base[$field] ?? null;
            $c = $current[$field] ?? null;
            $rows[] = [
                'feld' => (string) $field,
                'label' => self::FIELDS[$field] ?? (string) $field,
                'basis' => $b,
                'aktuell' => $c,
                'vorschlag' => $value,
                'konflikt' => $proposal->kind === 'change' && $this->norm($b) !== $this->norm($c),
                'erledigt' => $proposal->kind === 'change' && $this->norm($c) === $this->norm($value),
            ];
        }

        return $rows;
    }

    /** Personenbezug abgeschlossener Vorschläge nach Ablauf der Frist entfernen; Anzahl. */
    public function anonymizeResolvedBefore(DateTimeInterface $before): int
    {
        $ids = Proposal::query()->whereNotNull('resolved_at')->where('resolved_at', '<', $before)
            ->where(fn ($q) => $q->whereNotNull('user_id')->orWhere('author_label', '!=', Proposal::ANONYMOUS))
            ->whereNull('service_account_id')->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        $this->anonymize($ids);

        return count($ids);
    }

    /** Kontolöschung: Vorschläge der Person ohne Personenbezug (Journal::forget). */
    public static function forget(User $user): void
    {
        $ids = Proposal::query()->where('user_id', $user->id)->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        (new self(app(GlossaryService::class)))->anonymize($ids);
        ProposalEvent::query()->where('actor_id', $user->id)->toBase()->update(['actor_id' => null]);
    }

    // ---------- intern ----------

    /** @param  array<int, int>  $ids */
    private function anonymize(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        Proposal::query()->whereIn('id', $ids)->toBase()->update(['user_id' => null, 'author_label' => Proposal::ANONYMOUS]);
        ProposalEvent::query()->whereIn('proposal_id', $ids)->where('by_author', true)->toBase()
            ->update(['actor_id' => null, 'actor_label' => Proposal::ANONYMOUS]);
    }

    /**
     * Nur Felder der Positivliste; Texte getrimmt, Listen als Liste, Kategorien als bekannte Schlüssel.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function clean(array $values): array
    {
        $out = [];
        foreach (array_keys(self::FIELDS) as $field) {
            if (! array_key_exists($field, $values)) {
                continue;
            }
            $v = $values[$field];
            if (in_array($field, self::LIST_FIELDS, true)) {
                $list = is_array($v) ? $v : explode(';', (string) $v);
                $list = array_values(array_unique(array_filter(array_map(fn (mixed $x): string => trim((string) $x), $list), fn (string $x): bool => $x !== '')));
                $out[$field] = $list;
            } else {
                $text = trim((string) $v);
                if (mb_strlen($text) > ($field === 'description' ? 5000 : 255)) {
                    throw new InvalidArgumentException('«'.self::FIELDS[$field].'» ist zu lang.');
                }
                $out[$field] = $text;
            }
        }
        if (($out['short_description'] ?? '') !== '' && mb_strlen((string) $out['short_description']) > 160) {
            throw new InvalidArgumentException('Kurzbeschreibung höchstens 160 Zeichen.');
        }
        if (($out['url'] ?? '') !== '' && preg_match('#^https?://\S+$#i', (string) $out['url']) !== 1) {
            throw new InvalidArgumentException('URL muss mit http:// oder https:// beginnen.');
        }
        if (isset($out['categories'])) {
            $known = Category::query()->whereIn('key', $out['categories'])->pluck('key')->map(fn ($k): string => (string) $k)->all();
            $unknown = array_diff($out['categories'], $known);
            if ($unknown !== []) {
                throw new InvalidArgumentException('Unbekannte Kategorie: '.implode(', ', $unknown).'.');
            }
        }

        return $out;
    }

    /** Vergleichbarer Wert: Listen sortiert, Texte getrimmt, leer = ''. */
    private function norm(mixed $value): string
    {
        if (is_array($value)) {
            $list = array_map('strval', $value);
            sort($list);

            return implode("\n", $list);
        }

        return trim((string) $value);
    }

    private function requireOpen(Proposal $proposal): void
    {
        if (! $proposal->isOpen()) {
            throw new InvalidArgumentException('Dieser Vorschlag ist abgeschlossen.');
        }
    }

    private function requireAuthor(Proposal $proposal, User $user): void
    {
        if ($proposal->user_id !== $user->id) {
            throw new InvalidArgumentException('Nur die einreichende Person kann das.');
        }
    }

    private function requireText(string $text, string $message): void
    {
        if (trim($text) === '' || mb_strlen($text) > 2000) {
            throw new InvalidArgumentException($message);
        }
    }

    /** @param  array<string, mixed>|null  $details */
    private function event(Proposal $proposal, string $type, ?User $actor, bool $byAuthor, ?string $message = null, ?array $details = null, ?string $label = null): void
    {
        ProposalEvent::query()->create([
            'proposal_id' => $proposal->id,
            'type' => $type,
            'actor_id' => $actor?->id,
            'actor_label' => $label ?? Journal::actorLabel($actor),
            'by_author' => $byAuthor,
            'message' => $message !== null && trim($message) !== '' ? trim($message) : null,
            'details' => $details,
        ]);
    }
}
