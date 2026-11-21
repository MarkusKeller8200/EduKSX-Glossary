<?php

namespace App\Http\Controllers\Api;

use App\Access\Service\ServiceAccountManager;
use App\Http\Controllers\Controller;
use App\Models\ServiceAccountToken;
use App\Support\DatabaseDump;
use App\Sync\ContentState;
use App\Sync\SyncRole;
use App\Sync\UploadReceiver;
use EduKSX\Core\Http\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Entwicklungs-Sync, Seite der Gegenstelle (#478, DEC-041 4a): nur auf `master` und `test`, nur mit Token eines
 * Servicekontos und Scope. Interne Schnittstelle zwischen den eigenen Umgebungen, nicht Teil der öffentlichen API v1.
 */
class SyncApiController extends Controller
{
    public function __construct(private readonly ServiceAccountManager $accounts) {}

    /** GET /api/sync/state – Rolle, Scopes des Tokens und Prüfsummen (Scope glossary:sync.read oder .write). */
    public function state(Request $request, ContentState $state): JsonResponse
    {
        $token = $this->token($request);
        if (! $token->account->hasScope('glossary:sync.read') && ! $token->account->hasScope('glossary:sync.write')) {
            throw ProblemException::common(403, 'forbidden', 'Scope «glossary:sync.read» oder «glossary:sync.write» nötig.');
        }

        return response()->json([
            'role' => SyncRole::current(),
            'scopes' => array_values(array_filter(ServiceAccountManager::current($token->account->scopes), fn (string $s): bool => str_starts_with($s, 'glossary:sync.'))),
            ...$state->state(),
        ])->header('Cache-Control', 'no-store');
    }

    /** GET /api/sync/dump – anonymisierter Dump ohne Konten (Scope glossary:sync.read). */
    public function dump(Request $request): BinaryFileResponse
    {
        $token = $this->token($request);
        $this->requireSource();
        $file = tempnam(sys_get_temp_dir(), 'glossary-sync-dump-').'.zip';
        $manifest = DatabaseDump::create($file, null, null, true);
        $this->accounts->logUse($token, 'sync.dump', ['tables' => count($manifest['tables'])]);

        return response()->download($file, 'glossary-sync.zip', ['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store'])->deleteFileAfterSend();
    }

    /** POST /api/sync/upload – Begriffe und Listen als Entwurf bzw. veröffentlicht (Scope glossary:sync.write). */
    public function upload(Request $request, UploadReceiver $receiver): JsonResponse
    {
        $token = $this->token($request);
        $this->requireSource();
        $request->validate([
            'publish' => ['boolean'],
            'terms' => ['array', 'max:2000'],
            'terms.*.base_revision' => ['nullable', 'integer', 'min:0'],
            'terms.*.term' => ['required', 'array'],
            'terms.*.term.id' => ['required', 'uuid'],
            'categories' => ['array', 'max:500'],
            'categories.*' => ['array'],
            'sources' => ['array', 'max:500'],
            'sources.*' => ['array'],
        ]);
        // validate() liefert bei verschachtelten Regeln nur die geprüften Schlüssel; die Abbilder vollständig lesen
        $data = ['terms' => array_values((array) $request->input('terms', [])), 'categories' => array_values((array) $request->input('categories', [])), 'sources' => array_values((array) $request->input('sources', []))];
        $publish = $request->boolean('publish');
        if ($publish && ! $token->account->hasScope('glossary:sync.publish')) {
            throw ProblemException::common(403, 'forbidden', 'Für «direkt veröffentlichen» fehlt der Scope «glossary:sync.publish».');
        }
        /** @var array{terms?: list<array{base_revision: int|null, term: array<string, mixed>}>, categories?: list<array<string, mixed>>, sources?: list<array<string, mixed>>} $data */
        $result = $receiver->receive($data, 'Sync ('.$token->account->name.')', $publish);
        $count = fn (string $s): int => count(array_filter($result['terms'], fn (array $r): bool => str_starts_with($r['status'], $s)));
        $this->accounts->logUse($token, 'sync.upload', [
            'terms' => count($result['terms']), 'created' => $count('created'), 'updated' => $count('updated'),
            'conflicts' => $count('conflict'), 'publish' => $publish,
        ]);

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    private function token(Request $request): ServiceAccountToken
    {
        $token = $request->attributes->get('service_token');
        abort_unless($token instanceof ServiceAccountToken, 401);

        return $token;
    }

    /** Dump abgeben und Uploads annehmen nur als `master` bzw. `test` (A14, A19). */
    private function requireSource(): void
    {
        if (! SyncRole::isSource()) {
            throw ProblemException::common(403, 'forbidden', 'Diese Instanz ('.SyncRole::label(SyncRole::current()).') nimmt am Entwicklungs-Sync nicht als Quelle bzw. Ziel teil.');
        }
    }
}
