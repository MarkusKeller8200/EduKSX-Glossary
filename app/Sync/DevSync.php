<?php

namespace App\Sync;

use App\Access\Audit;
use App\Models\Category;
use App\Models\Source;
use App\Models\SyncPeer;
use App\Models\Term;
use App\Models\User;
use App\Support\DatabaseDump;
use App\Support\DumpArchive;
use App\Support\DumpRestore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Entwicklungs-Sync auf der startenden Seite (dev bzw. test; #478, #503, DEC-041 4a).
 *
 * - **Ersetzen** (bisher «Holen», A13, A14): Sicherung, anonymisierten Dump der Gegenstelle laden und einspielen;
 *   Konten, Einstellungen, Servicekonten und Gegenstellen bleiben. Danach gilt der Stand der Gegenstelle als Basis.
 * - **Vergleichen** (#503): beide Seiten lesen; je Begriff bzw. Listeneintrag Zustand (nur hier, nur dort, hier bzw.
 *   dort geändert, Konflikt), Zeitstempel beider Seiten und eine vorgeschlagene Richtung (jüngerer Stand gewinnt).
 * - **Synchronisieren** (#503): je Datensatz in der gewählten Richtung anlegen bzw. aktualisieren (neue Revision),
 *   Status wie auf der Quelle; nie löschen. Danach gilt jeder gleiche Datensatz als Basis für die Konfliktprüfung.
 */
final class DevSync
{
    /** Tabellen, die beim Holen zusätzlich zu den Konten unverändert bleiben (A13) */
    public const KEEP = [
        'settings', 'service_accounts', 'service_account_tokens', 'service_account_audit', 'sync_peers', 'mail_log',
        'received_events', 'api_idempotency_keys',
    ];

    /** Listen im Sync: Name im Stand → Modell */
    public const LISTS = ['categories' => Category::class, 'sources' => Source::class];

    public function __construct(private readonly ContentState $state, private readonly UploadReceiver $receiver) {}

    /**
     * @return array{tables: int, rows: int, dropped: int, backup: string}
     *
     * @throws RuntimeException
     */
    public function pull(SyncPeer $peer, User $actor, ?string $backupPassword = null): array
    {
        $client = new PeerClient($peer);
        $remote = $client->state();
        if (! SyncRole::canPullFrom($remote['role'])) {
            throw new RuntimeException('Holen von «'.SyncRole::label($remote['role']).'» ist als «'.SyncRole::label(SyncRole::current()).'» nicht erlaubt.');
        }
        if (! in_array('glossary:sync.read', $remote['scopes'], true)) {
            throw new RuntimeException('Dem Token fehlt der Scope «glossary:sync.read».');
        }

        $backup = $this->backup('vor-holen', $backupPassword);

        $file = tempnam(sys_get_temp_dir(), 'glossary-sync-').'.zip';
        try {
            $client->dump($file);
            $dump = DumpArchive::open($file);
            if (! $dump->anonymized()) {
                throw new RuntimeException('Die Gegenstelle hat keinen anonymisierten Dump geliefert.');
            }
            // die Gegenstelle bleibt beim Einspielen erhalten (sync_peers ist behalten)
            $result = DumpRestore::runKeeping($dump, DB::connection(), self::KEEP);
            $dump->close();
        } finally {
            @unlink($file);
        }
        Artisan::call('cache:clear');

        $peer = SyncPeer::query()->findOrFail($peer->id);
        $peer->base = ['terms' => $remote['terms'], 'categories' => $remote['categories'], 'sources' => $remote['sources']];
        $peer->remote_role = $remote['role'];
        $peer->last_pulled_at = now()->toImmutable();
        $rows = array_sum($result['tables']);
        $dropped = array_sum($result['dropped']);
        $peer->last_result = "Geholt: {$rows} Zeilen in ".count($result['tables']).' Tabellen'.($dropped > 0 ? ", {$dropped} ohne Konto entfallen" : '').'.';
        $peer->save();
        Audit::log($actor, null, 'sync.pulled', ['peer' => $peer->name, 'role' => $remote['role'], 'rows' => $rows, 'backup' => $backup]);

        return ['tables' => count($result['tables']), 'rows' => $rows, 'dropped' => $dropped, 'backup' => $backup];
    }

    /**
     * Vergleich mit der Gegenstelle (#503).
     *
     * @return array{role: string, push: bool, pull: bool, publish: bool, rows: list<array<string, mixed>>}
     */
    public function compare(SyncPeer $peer): array
    {
        $remote = (new PeerClient($peer))->state();
        $local = $this->state->state();

        return [
            'role' => $remote['role'],
            'push' => self::mayPush($remote),
            'pull' => self::mayPull($remote),
            'publish' => in_array('glossary:sync.publish', $remote['scopes'], true),
            'rows' => $this->rows($local, $remote, (array) ($peer->base ?? []), self::mayPush($remote), self::mayPull($remote), $peer),
        ];
    }

    /**
     * Gewählte Datensätze synchronisieren (#503). `$choices`: Liste aus Typ (`term`, `categories`, `sources`), ID bzw.
     * Schlüssel und Richtung (`push` = hier → dort, `pull` = dort → hier).
     *
     * @param  list<array{type: string, id: string, direction: string}>  $choices
     * @return array{pushed: int, pulled: int, conflicts: list<string>, errors: list<string>, messages: list<string>, backup: string|null}
     *
     * @throws RuntimeException
     */
    public function sync(SyncPeer $peer, array $choices, User $actor, ?string $backupPassword = null): array
    {
        $client = new PeerClient($peer);
        $remote = $client->state();
        $local = $this->state->state();
        $push = ['terms' => [], 'categories' => [], 'sources' => []];
        $pull = ['terms' => [], 'categories' => [], 'sources' => []];
        foreach ($choices as $c) {
            $list = $c['type'] === 'term' ? 'terms' : $c['type'];
            if (! isset($push[$list]) || ! in_array($c['direction'], ['push', 'pull'], true)) {
                continue;
            }
            $here = $local[$list][$c['id']] ?? null;
            $there = $remote[$list][$c['id']] ?? null;
            // nur, was sich (noch) unterscheidet und auf der Quellseite vorhanden ist
            if (ContentState::hashOf($here, $list === 'terms') === ContentState::hashOf($there, $list === 'terms')) {
                continue;
            }
            if ($c['direction'] === 'push' && $here !== null) {
                $push[$list][] = $c['id'];
            } elseif ($c['direction'] === 'pull' && $there !== null) {
                $pull[$list][] = $c['id'];
            }
        }
        $count = fn (array $a): int => count($a['terms']) + count($a['categories']) + count($a['sources']);
        if ($count($push) === 0 && $count($pull) === 0) {
            throw new RuntimeException('Nichts zu synchronisieren (alle Zeilen auf «überspringen» oder bereits gleich).');
        }
        if ($count($push) > 0 && ! self::mayPush($remote)) {
            throw new RuntimeException('Nach «'.SyncRole::label($remote['role']).'» darf diese Instanz («'.SyncRole::label(SyncRole::current()).'») nicht schreiben, oder dem Token fehlt «glossary:sync.write».');
        }
        if ($count($pull) > 0 && ! self::mayPull($remote)) {
            throw new RuntimeException('Von «'.SyncRole::label($remote['role']).'» darf diese Instanz («'.SyncRole::label(SyncRole::current()).'») nicht übernehmen, oder dem Token fehlt «glossary:sync.read».');
        }

        $summary = ['pushed' => 0, 'pulled' => 0, 'conflicts' => [], 'errors' => [], 'messages' => [], 'backup' => null];
        $titles = Term::query()->whereIn('id', [...$push['terms'], ...$pull['terms']])->get()->mapWithKeys(fn (Term $t): array => [$t->id => $t->title()]);
        $collect = function (array $response, string $direction) use (&$summary, $titles): void {
            foreach ((array) ($response['terms'] ?? []) as $r) {
                $r = (array) $r;
                $status = (string) ($r['status'] ?? '');
                $name = (string) ($titles[(string) ($r['id'] ?? '')] ?? ($r['id'] ?? ''));
                if (in_array(str_replace('+published', '', $status), ['created', 'updated', 'unchanged'], true)) {
                    $summary[$direction]++;
                } elseif ($status === 'conflict') {
                    $summary['conflicts'][] = $name;
                } else {
                    $summary['errors'][] = $name.': '.($r['message'] ?? 'Fehler');
                }
                if (is_string($r['message'] ?? null) && $r['message'] !== '' && ! in_array($status, ['conflict', 'error'], true)) {
                    $summary['messages'][] = $name.': '.$r['message'];
                }
            }
            foreach (array_keys(self::LISTS) as $list) {
                $summary[$direction] += count((array) ($response[$list] ?? []));
            }
        };

        // hier → dort: Basis der Konfliktprüfung ist der Stand der Gegenstelle beim Vergleich direkt davor
        if ($count($push) > 0) {
            $payload = ['publish' => false, 'terms' => [], 'categories' => [], 'sources' => []];
            foreach (Term::query()->whereIn('id', $push['terms'])->with(['categories', 'sources', 'aliases', 'translations', 'termRelations'])->get() as $term) {
                $payload['terms'][] = [
                    'base_revision' => isset($remote['terms'][$term->id]) ? $remote['terms'][$term->id][0] : null,
                    'term' => $this->state->term($term),
                    'publish' => (bool) ($local['terms'][$term->id][3] ?? false),
                ];
            }
            foreach (self::LISTS as $list => $model) {
                $payload[$list] = array_values(array_intersect_key($this->state->listItems($model), array_flip($push[$list])));
            }
            $collect($client->upload($payload), 'pushed');
        }

        // dort → hier: vorher Sicherung, dann mit derselben Logik wie beim Empfangen übernehmen
        if ($count($pull) > 0) {
            $summary['backup'] = $this->backup('vor-sync', $backupPassword);
            $items = $client->items($pull);
            $upload = ['terms' => [], 'categories' => $items['categories'], 'sources' => $items['sources']];
            foreach ($items['terms'] as $t) {
                $id = (string) ($t['term']['id'] ?? '');
                $upload['terms'][] = ['base_revision' => isset($local['terms'][$id]) ? $local['terms'][$id][0] : null, 'term' => $t['term'], 'publish' => $t['published']];
            }
            /** @var array{terms?: list<array{base_revision: int|null, term: array<string, mixed>, publish?: bool}>, categories?: list<array<string, mixed>>, sources?: list<array<string, mixed>>} $upload */
            $collect($this->receiver->receive($upload, 'Sync von «'.$peer->name.'»', false, true), 'pulled');
            Artisan::call('cache:clear');
        }

        // neue Basis: jeder Datensatz, der jetzt auf beiden Seiten gleich ist
        $peer = SyncPeer::query()->findOrFail($peer->id);
        $peer->base = $this->newBase((array) ($peer->base ?? []), $this->state->state(), $client->state());
        $peer->remote_role = $remote['role'];
        $peer->last_pushed_at = $count($push) > 0 ? now()->toImmutable() : $peer->last_pushed_at;
        $peer->last_pulled_at = $count($pull) > 0 ? now()->toImmutable() : $peer->last_pulled_at;
        $peer->last_result = "Synchronisiert: {$summary['pushed']} nach «{$peer->name}», {$summary['pulled']} von dort"
            .($summary['conflicts'] !== [] ? ', '.count($summary['conflicts']).' Konflikte' : '')
            .($summary['errors'] !== [] ? ', '.count($summary['errors']).' Fehler' : '').'.';
        $peer->save();
        Audit::log($actor, null, 'sync.synchronized', ['peer' => $peer->name, 'role' => $remote['role'], 'pushed' => $summary['pushed'],
            'pulled' => $summary['pulled'], 'conflicts' => count($summary['conflicts']), 'errors' => count($summary['errors']), 'backup' => $summary['backup']]);

        return $summary;
    }

    /**
     * Beide Fassungen eines Datensatzes für die Detailansicht (#503).
     *
     * @return array{here: array<string, mixed>|null, there: array<string, mixed>|null}
     */
    public function detail(SyncPeer $peer, string $type, string $id): array
    {
        if ($type === 'term') {
            $term = Term::query()->current()->find($id);
            $there = (new PeerClient($peer))->items(['terms' => [$id]])['terms'][0]['term'] ?? null;

            return ['here' => $term instanceof Term ? $this->state->term($term) : null, 'there' => $there];
        }
        if (! isset(self::LISTS[$type])) {
            throw new RuntimeException('Unbekannter Typ.');
        }

        return [
            'here' => $this->state->listItems(self::LISTS[$type])[$id] ?? null,
            'there' => (new PeerClient($peer))->items([$type => [$id]])[$type][0] ?? null,
        ];
    }

    /**
     * Zustand eines Datensatzes aus eigener Prüfsumme, der der Gegenstelle und der Basis.
     *
     * same · only_here · only_there · changed_here · changed_there · conflict (beide seit der Basis geändert) ·
     * different (beide vorhanden, verschieden, ohne Basis)
     */
    public static function classify(?string $here, ?string $there, ?string $base): string
    {
        return match (true) {
            $here === $there => 'same',
            $there === null => 'only_here',
            $here === null => 'only_there',
            $base === null => 'different',
            $there === $base => 'changed_here',
            $here === $base => 'changed_there',
            default => 'conflict',
        };
    }

    /**
     * Vorgeschlagene Richtung: «nur hier» → hinüber, «nur dort» → herüber, sonst der jüngere Stand; nur, was erlaubt ist.
     */
    public static function suggest(string $state, ?string $hereAt, ?string $thereAt, bool $push, bool $pull): string
    {
        $direction = match ($state) {
            'only_here', 'changed_here' => 'push',
            'only_there', 'changed_there' => 'pull',
            default => match (true) {
                $hereAt === null && $thereAt === null => 'skip',
                $hereAt === null => 'pull',
                $thereAt === null => 'push',
                default => CarbonImmutable::parse($thereAt)->greaterThan(CarbonImmutable::parse($hereAt)) ? 'pull' : 'push',
            },
        };

        return match ($direction) {
            'push' => $push ? 'push' : 'skip',
            'pull' => $pull ? 'pull' : 'skip',
            default => 'skip',
        };
    }

    /** @param  array{role: string, scopes: list<string>}  $remote */
    private static function mayPush(array $remote): bool
    {
        return SyncRole::canUploadTo($remote['role']) && in_array('glossary:sync.write', $remote['scopes'], true);
    }

    /** @param  array{role: string, scopes: list<string>}  $remote */
    private static function mayPull(array $remote): bool
    {
        return SyncRole::canPullFrom($remote['role']) && in_array('glossary:sync.read', $remote['scopes'], true);
    }

    /**
     * @param  array<string, mixed>  $local
     * @param  array<string, mixed>  $remote
     * @param  array<string, mixed>  $base
     * @return list<array<string, mixed>>
     */
    private function rows(array $local, array $remote, array $base, bool $push, bool $pull, ?SyncPeer $peer = null): array
    {
        $rows = [];
        $titles = Term::query()->current()->get(['id', 'official_abbreviation', 'official_name'])->mapWithKeys(fn (Term $t): array => [$t->id => $t->title()]);
        $missingTitles = [];
        foreach (array_unique([...array_keys((array) $local['terms']), ...array_keys((array) $remote['terms'])]) as $id) {
            $here = $local['terms'][$id] ?? null;
            $there = $remote['terms'][$id] ?? null;
            $baseHash = ContentState::hashOf(((array) ($base['terms'] ?? []))[$id] ?? null, true);
            $state = self::classify(ContentState::hashOf($here, true), ContentState::hashOf($there, true), $baseHash);
            if ($state === 'same') {
                continue;
            }
            $hereAt = $here[2] ?? null;
            $thereAt = $there[2] ?? null;
            if (! isset($titles[$id])) {
                $missingTitles[] = (string) $id;
            }
            $rows[] = [
                'type' => 'term', 'id' => (string) $id, 'title' => $titles[$id] ?? null, 'state' => $state,
                'here' => $here === null ? null : ['at' => $hereAt, 'published' => (bool) ($here[3] ?? false), 'revision' => (int) $here[0]],
                'there' => $there === null ? null : ['at' => $thereAt, 'published' => (bool) ($there[3] ?? false), 'revision' => (int) $there[0]],
                'direction' => self::suggest($state, $hereAt, $thereAt, $push, $pull),
                'synced_before' => $baseHash !== null,
            ];
        }
        foreach (self::LISTS as $list => $model) {
            $labels = $model::query()->get()->mapWithKeys(fn (Category|Source $i): array => [(string) $i->key => $i->displayLabel()]);
            foreach (array_unique([...array_keys((array) $local[$list]), ...array_keys((array) $remote[$list])]) as $key) {
                $here = $local[$list][$key] ?? null;
                $there = $remote[$list][$key] ?? null;
                $baseHash = ContentState::hashOf(((array) ($base[$list] ?? []))[$key] ?? null, false);
                $state = self::classify(ContentState::hashOf($here, false), ContentState::hashOf($there, false), $baseHash);
                if ($state === 'same') {
                    continue;
                }
                $hereAt = is_array($here) ? ($here[1] ?? null) : null;
                $thereAt = is_array($there) ? ($there[1] ?? null) : null;
                $rows[] = [
                    'type' => $list, 'id' => (string) $key, 'title' => (string) ($labels[$key] ?? $key), 'state' => $state,
                    'here' => $here === null ? null : ['at' => $hereAt, 'published' => null, 'revision' => null],
                    'there' => $there === null ? null : ['at' => $thereAt, 'published' => null, 'revision' => null],
                    'direction' => self::suggest($state, $hereAt, $thereAt, $push, $pull),
                    'synced_before' => $baseHash !== null,
                ];
            }
        }
        // Titel von Begriffen, die es nur dort gibt, mit einem Abruf bei der Gegenstelle ergänzen (nur Anzeige)
        if ($missingTitles !== [] && $peer !== null) {
            try {
                $found = [];
                foreach (array_chunk($missingTitles, 2000) as $chunk) {
                    foreach ((new PeerClient($peer))->items(['terms' => $chunk])['terms'] as $t) {
                        $found[(string) ($t['term']['id'] ?? '')] = Term::titleFrom($t['term']);
                    }
                }
                foreach ($rows as $n => $row) {
                    if ($row['type'] === 'term' && $row['title'] === null && ($found[$row['id']] ?? '') !== '') {
                        $rows[$n]['title'] = $found[$row['id']];
                    }
                }
            } catch (Throwable) {
                // ohne Titel weiter, die ID wird angezeigt
            }
        }
        foreach ($rows as $n => $row) {
            $rows[$n]['title'] ??= $row['id'];
        }
        usort($rows, fn (array $a, array $b): int => [$a['type'] !== 'term', mb_strtolower((string) $a['title'])] <=> [$b['type'] !== 'term', mb_strtolower((string) $b['title'])]);

        return $rows;
    }

    /**
     * Basis nach dem Sync: alte Basis, dazu jeder Datensatz, der auf beiden Seiten gleich ist (Stand der Gegenstelle).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $local
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>
     */
    private function newBase(array $old, array $local, array $remote): array
    {
        $base = $old;
        foreach (['terms', ...array_keys(self::LISTS)] as $list) {
            foreach ((array) $remote[$list] as $id => $entry) {
                if (isset($local[$list][$id]) && ContentState::hashOf($local[$list][$id], $list === 'terms') === ContentState::hashOf($entry, $list === 'terms')) {
                    $base[$list][(string) $id] = $entry;
                }
            }
        }

        return $base;
    }

    /** Sicherung vor Änderungen auf dieser Instanz. */
    private function backup(string $prefix, ?string $password): string
    {
        $dir = storage_path('app/backups/sync');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $name = $prefix.'-'.now()->utc()->format('Ymd\THis\Z').'.zip';
        DatabaseDump::create($dir.'/'.$name, $password);

        return $name;
    }
}
