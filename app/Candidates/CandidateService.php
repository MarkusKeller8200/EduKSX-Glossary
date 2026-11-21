<?php

namespace App\Candidates;

use App\Models\Term;
use App\Models\TermCandidate;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Kandidaten aus Erweiterung und Einbindung (#480, DEC-042): melden (Duplikate zählen nur hoch, Verworfenes kommt
 * nicht wieder), sichten und entscheiden. Übertragen werden nur Wort, Kommentar und Adresse ohne Abfrage.
 */
final class CandidateService
{
    public const MAX_WORD = 80;

    public const MAX_COMMENT = 500;

    /** höchstens so viele Kommentare je Kandidat (die neuesten) */
    private const MAX_COMMENTS = 10;

    /** Adresse ohne Abfrageparameter und Anker, nur http(s); sonst null. */
    public static function cleanUrl(?string $url): ?string
    {
        $parts = parse_url(trim((string) $url));
        if ($parts === false || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ! isset($parts['host'])) {
            return null;
        }
        $clean = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '/');

        return mb_substr($clean, 0, 500);
    }

    public static function normalize(string $word): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $word)));
    }

    /** Wort gesperrt (Einstellung «Gesperrte Wörter»)? */
    public static function blocked(string $word): bool
    {
        $normalized = self::normalize($word);
        foreach (explode(',', Settings::string('candidates.blocklist')) as $entry) {
            $entry = self::normalize($entry);
            if ($entry !== '' && str_contains($normalized, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Meldung annehmen. Gibt den Kandidaten zurück (neu oder hochgezählt); null bei gesperrtem Wort.
     *
     * @throws InvalidArgumentException bei ungültigen Angaben
     */
    public function report(string $word, string $kind, string $channel, ?string $code, ?string $comment, ?string $url, ?User $user): ?TermCandidate
    {
        $word = trim((string) preg_replace('/\s+/u', ' ', strip_tags($word)));
        if ($word === '' || mb_strlen($word) > self::MAX_WORD) {
            throw new InvalidArgumentException('Wort mit 1 bis '.self::MAX_WORD.' Zeichen angeben.');
        }
        if (! in_array($kind, ['missing', 'wrong'], true) || ! in_array($channel, ['extension', 'embed'], true)) {
            throw new InvalidArgumentException('Unbekannte Art der Meldung.');
        }
        $comment = $comment === null ? null : trim(strip_tags($comment));
        if ($comment !== null && mb_strlen($comment) > self::MAX_COMMENT) {
            throw new InvalidArgumentException('Kommentar höchstens '.self::MAX_COMMENT.' Zeichen.');
        }
        $term = null;
        if ($kind === 'wrong') {
            $term = $code !== null ? Term::query()->published()->current()->where('code', strtoupper($code))->first() : null;
            if ($term === null) {
                throw new InvalidArgumentException('Begriff zur Meldung «Erklärung falsch» nicht gefunden.');
            }
        }
        if (self::blocked($word)) {
            return null;
        }
        $url = self::cleanUrl($url);
        $normalized = mb_substr(self::normalize($word), 0, self::MAX_WORD);

        return DB::transaction(function () use ($word, $normalized, $kind, $channel, $term, $comment, $url, $user): TermCandidate {
            // gleiches Wort, gleiche Art (und gleicher Begriff): nur hochzählen; Verworfenes bleibt verworfen
            $existing = TermCandidate::query()->where('normalized', $normalized)->where('kind', $kind)
                ->where('term_id', $term?->id)->whereIn('status', ['new', 'rejected'])
                ->orderByRaw("case when status = 'new' then 0 else 1 end")->lockForUpdate()->first();
            $candidate = $existing ?? new TermCandidate([]);
            if ($existing === null) {
                $candidate->word = $word;
                $candidate->normalized = $normalized;
                $candidate->kind = $kind;
                $candidate->channel = $channel;
                $candidate->term_id = $term?->id;
                $candidate->first_url = $url;
                $candidate->reported_by_id = $user?->id;
            } else {
                $candidate->count++;
                $candidate->reported_by_id ??= $user?->id;
            }
            $candidate->last_url = $url ?? $candidate->last_url;
            $candidate->last_seen_at = now()->toImmutable();
            if ($comment !== null && $comment !== '') {
                $candidate->comments = array_slice([...($candidate->comments ?? []), $comment], -self::MAX_COMMENTS);
            }
            $candidate->save();

            return $candidate;
        });
    }

    /** Verwerfen (kommt nicht wieder) bzw. als erledigt markieren. */
    public function decide(TermCandidate $candidate, string $status, ?string $reason, User $user, ?Term $result = null): void
    {
        if (! in_array($status, ['accepted', 'rejected', 'alias'], true)) {
            throw new InvalidArgumentException('Unbekannter Entscheid.');
        }
        $candidate->status = $status;
        $candidate->reason = $reason === null || trim($reason) === '' ? null : mb_substr(trim($reason), 0, 500);
        $candidate->result_term_id = $result !== null ? $result->id : $candidate->result_term_id;
        $candidate->decided_by_id = $user->id;
        $candidate->decided_at = now()->toImmutable();
        $candidate->save();
    }
}
