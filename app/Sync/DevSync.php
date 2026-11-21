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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Entwicklungs-Sync auf der holenden bzw. hochladenden Seite (#478, DEC-041 4a).
 *
 * - **Holen** (A13, A14): Sicherung, anonymisierten Dump der Gegenstelle laden und einspielen; Konten, Einstellungen,
 *   Servicekonten und Gegenstellen bleiben. Danach gilt der Stand der Gegenstelle als Basis.
 * - **Vergleichen** (A16, A17): je Begriff bzw. Listeneintrag eigener Stand, Stand der Gegenstelle und Basis.
 * - **Hochladen**: nur, wenn sich die Gegenstelle seit der Basis nicht geändert hat; sonst zuerst holen.
 */
final class DevSync
{
    /** Tabellen, die beim Holen zusätzlich zu den Konten unverändert bleiben (A13) */
    public const KEEP = [
        'settings', 'service_accounts', 'service_account_tokens', 'service_account_audit', 'sync_peers', 'mail_log',
        'received_events', 'api_idempotency_keys',
    ];

    public function __construct(private readonly ContentState $state) {}

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

        $dir = storage_path('app/backups/sync');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $backup = 'vor-holen-'.now()->utc()->format('Ymd\THis\Z').'.zip';
        DatabaseDump::create($dir.'/'.$backup, $backupPassword);

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
     * Vergleich mit der Gegenstelle.
     *
     * @return array{role: string, publish: bool, upload: bool, blocked: bool, terms: list<array{id: string, title: string, state: string}>, lists: list<array{list: string, key: string, title: string, state: string}>}
     */
    public function compare(SyncPeer $peer): array
    {
        $remote = (new PeerClient($peer))->state();
        $local = $this->state->state();
        $base = (array) ($peer->base ?? []);

        $terms = [];
        $titles = Term::query()->current()->get(['id', 'official_abbreviation', 'official_name'])->keyBy('id');
        foreach (array_unique([...array_keys($local['terms']), ...array_keys($remote['terms'])]) as $id) {
            $state = self::classify($local['terms'][$id][1] ?? null, $remote['terms'][$id][1] ?? null, ((array) ($base['terms'] ?? []))[$id][1] ?? null);
            if ($state !== 'same') {
                $t = $titles->get($id);
                $terms[] = ['id' => (string) $id, 'title' => $t instanceof Term ? $t->title() : '(nur auf der Gegenstelle)', 'state' => $state];
            }
        }
        $lists = [];
        foreach (['categories' => Category::class, 'sources' => Source::class] as $list => $model) {
            $labels = $model::query()->get()->mapWithKeys(fn (Category|Source $i): array => [(string) $i->key => $i->displayLabel()]);
            foreach (array_unique([...array_keys($local[$list]), ...array_keys($remote[$list])]) as $key) {
                $state = self::classify($local[$list][$key] ?? null, $remote[$list][$key] ?? null, ((array) ($base[$list] ?? []))[$key] ?? null);
                if ($state !== 'same') {
                    $lists[] = ['list' => $list, 'key' => (string) $key, 'title' => (string) ($labels[$key] ?? $key), 'state' => $state];
                }
            }
        }
        usort($terms, fn (array $a, array $b): int => [$a['state'], mb_strtolower($a['title'])] <=> [$b['state'], mb_strtolower($b['title'])]);
        $blocked = array_filter([...$terms, ...$lists], fn (array $i): bool => in_array($i['state'], ['remote', 'conflict'], true)) !== [];

        return [
            'role' => $remote['role'],
            'publish' => in_array('glossary:sync.publish', $remote['scopes'], true),
            'upload' => SyncRole::canUploadTo($remote['role']) && in_array('glossary:sync.write', $remote['scopes'], true),
            'blocked' => $blocked,
            'terms' => $terms,
            'lists' => $lists,
        ];
    }

    /**
     * Ausgewählte Begriffe (und alle lokal geänderten Listeneinträge) hochladen.
     *
     * @param  list<string>  $ids
     * @return array{created: int, updated: int, unchanged: int, conflicts: list<string>, errors: list<string>, messages: list<string>}
     *
     * @throws RuntimeException
     */
    public function upload(SyncPeer $peer, array $ids, bool $publish, User $actor): array
    {
        $compare = $this->compare($peer);
        if (! SyncRole::canUploadTo($compare['role'])) {
            throw new RuntimeException('Hochladen nach «'.SyncRole::label($compare['role']).'» ist als «'.SyncRole::label(SyncRole::current()).'» nicht erlaubt.');
        }
        if (! $compare['upload']) {
            throw new RuntimeException('Dem Token fehlt der Scope «glossary:sync.write».');
        }
        if ($compare['blocked']) {
            throw new RuntimeException('Die Gegenstelle hat sich seit dem letzten Holen geändert – zuerst «Holen».');
        }
        if ($publish && ! $compare['publish']) {
            throw new RuntimeException('Dem Token fehlt der Scope «glossary:sync.publish» für «direkt veröffentlichen».');
        }
        $ready = array_column(array_filter($compare['terms'], fn (array $t): bool => in_array($t['state'], ['local', 'new'], true)), 'id');
        $ids = array_values(array_intersect($ids, $ready));
        $base = (array) ($peer->base ?? []);

        $payload = ['publish' => $publish, 'terms' => [], 'categories' => [], 'sources' => []];
        foreach (Term::query()->whereIn('id', $ids)->get() as $term) {
            $payload['terms'][] = ['base_revision' => ((array) ($base['terms'] ?? []))[$term->id][0] ?? null, 'term' => $this->state->term($term)];
        }
        foreach (['categories' => Category::class, 'sources' => Source::class] as $list => $model) {
            $items = $this->state->listItems($model);
            foreach ($compare['lists'] as $l) {
                if ($l['list'] === $list && in_array($l['state'], ['local', 'new'], true) && isset($items[$l['key']])) {
                    $payload[$list][] = $items[$l['key']];
                }
            }
        }
        if ($payload['terms'] === [] && $payload['categories'] === [] && $payload['sources'] === []) {
            throw new RuntimeException('Nichts zum Hochladen ausgewählt.');
        }

        $response = (new PeerClient($peer))->upload($payload);
        $summary = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'conflicts' => [], 'errors' => [], 'messages' => []];
        $newBase = $base;
        foreach ((array) ($response['terms'] ?? []) as $r) {
            $r = (array) $r;
            $status = (string) ($r['status'] ?? '');
            $id = (string) ($r['id'] ?? '');
            $plain = str_replace('+published', '', $status);
            if (in_array($plain, ['created', 'updated', 'unchanged'], true)) {
                $summary[$plain]++;
                $newBase['terms'][$id] = [(int) ($r['revision'] ?? 0), (string) ($r['hash'] ?? '')];
            } elseif ($status === 'conflict') {
                $summary['conflicts'][] = $id;
            } else {
                $summary['errors'][] = $id.': '.($r['message'] ?? 'Fehler');
            }
            if (is_string($r['message'] ?? null) && $status !== 'conflict' && $status !== 'error') {
                $summary['messages'][] = (string) $r['message'];
            }
        }
        foreach (['categories', 'sources'] as $list) {
            foreach ((array) ($response[$list] ?? []) as $key => $hash) {
                $newBase[$list][(string) $key] = (string) $hash;
            }
        }
        $peer->base = $newBase;
        $peer->last_pushed_at = now()->toImmutable();
        $peer->last_result = "Hochgeladen: {$summary['created']} neu, {$summary['updated']} geändert"
            .($summary['conflicts'] !== [] ? ', '.count($summary['conflicts']).' Konflikte' : '')
            .($summary['errors'] !== [] ? ', '.count($summary['errors']).' Fehler' : '').($publish ? ' (veröffentlicht)' : ' (Entwurf)').'.';
        $peer->save();
        Audit::log($actor, null, 'sync.uploaded', ['peer' => $peer->name, 'role' => $compare['role'], 'terms' => count($payload['terms']), 'publish' => $publish,
            'created' => $summary['created'], 'updated' => $summary['updated'], 'conflicts' => count($summary['conflicts'])]);

        return $summary;
    }

    /**
     * Zustand eines Eintrags aus eigener Prüfsumme, der der Gegenstelle und der Basis.
     *
     * same: gleich · new: nur hier · local: hier geändert · remote: dort geändert · conflict: beide geändert ·
     * missing: nur dort, hier unverändert gelöscht
     */
    public static function classify(?string $local, ?string $remote, ?string $base): string
    {
        return match (true) {
            $local === $remote => 'same',
            $remote === null && $base === null => 'new',
            $remote === $base => 'local',
            $local === $base => 'remote',
            default => 'conflict',
        };
    }
}
