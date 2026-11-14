<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Idempotency;
use App\Models\Proposal;
use App\Models\ServiceAccountToken;
use App\Models\Term;
use App\Proposals\ProposalService;
use EduKSX\Core\Http\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Vorschläge per Servicekonto (#113, #47, Scope «propose»): landen in der Moderation, nie direkt öffentlich.
 * Ein Servicekonto sieht nur den Stand seiner eigenen Vorschläge.
 */
class ProposalApiController extends Controller
{
    public function __construct(private readonly ProposalService $proposals) {}

    public function store(Request $request): JsonResponse
    {
        $token = $this->token($request);

        // Idempotency-Key (B3, #348): Wiederholung liefert dieselbe Antwort ohne zweiten Vorschlag
        return Idempotency::handle($request, $token->account, fn (): JsonResponse => $this->create($request, $token));
    }

    private function create(Request $request, ServiceAccountToken $token): JsonResponse
    {
        $data = $request->validate([
            'term' => ['nullable', 'string', 'max:200'],
            'changes' => ['required', 'array'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $term = null;
        if (($data['term'] ?? '') !== '') {
            $term = Term::query()->published()->whereNull('merged_into_id')
                ->idOrSlug((string) $data['term'])->with('publishedRevision')->first();
            if ($term === null) {
                throw ProblemException::common(404, 'not_found', 'Begriff nicht gefunden oder nicht veröffentlicht.');
            }
        }
        try {
            $proposal = $this->proposals->submit($term, (array) $data['changes'], $data['reason'] ?? null, null, $token->account);
        } catch (InvalidArgumentException $e) {
            throw ProblemException::common(422, 'validation_failed', $e->getMessage(), [], ['changes' => [$e->getMessage()]]);
        }

        return response()->json($this->present($proposal), 201)
            ->header('Cache-Control', 'no-store')->header('ETag', self::etag($proposal));
    }

    /** GET /api/v1/proposals/{id} – nur über die UUID (#322); die frühere Zahl ergibt 404. */
    public function show(Request $request, string $id): JsonResponse
    {
        $proposal = $this->own($request, $id);

        return response()->json($this->present($proposal))
            ->header('Cache-Control', 'no-store')->header('ETag', self::etag($proposal));
    }

    /**
     * DELETE /api/v1/proposals/{id} – offenen Vorschlag zurückziehen (#347). Verlangt If-Match mit dem ETag aus GET
     * bzw. POST: fehlt er 428, ist er veraltet 412 mit dem aktuellen ETag (API-Standard B2).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $token = $this->token($request);
        $proposal = $this->own($request, $id);
        $current = self::etag($proposal);
        $header = trim((string) $request->headers->get('If-Match', ''));
        if ($header === '') {
            throw ProblemException::common(428, 'precondition_required', 'If-Match mit dem ETag des Vorschlags fehlt.', ['ETag' => $current]);
        }
        $given = array_map('trim', explode(',', $header));
        if (! in_array('*', $given, true) && ! in_array($current, $given, true)) {
            throw ProblemException::common(412, 'precondition_failed', 'Der Vorschlag wurde inzwischen geändert.', ['ETag' => $current]);
        }
        if (! $proposal->isOpen()) {
            throw ProblemException::common(409, 'conflict', 'Der Vorschlag ist abgeschlossen und lässt sich nicht mehr zurückziehen.', ['ETag' => $current]);
        }
        $this->proposals->withdrawByService($proposal, $token->account);
        $proposal->refresh();

        return response()->json($this->present($proposal))
            ->header('Cache-Control', 'no-store')->header('ETag', self::etag($proposal));
    }

    /** Starker Objekt-ETag: öffentliche Kennung und Stand (B2) */
    public static function etag(Proposal $proposal): string
    {
        return '"'.$proposal->uuid.'.'.(int) $proposal->lock_version.'"';
    }

    private function own(Request $request, string $id): Proposal
    {
        $token = $this->token($request);
        $proposal = Str::isUuid($id)
            ? Proposal::query()->where('uuid', strtolower($id))->where('service_account_id', $token->account->id)->first()
            : null;
        if ($proposal === null) {
            throw ProblemException::common(404, 'not_found', 'Vorschlag nicht gefunden.');
        }

        return $proposal;
    }

    /** @return array<string, mixed> */
    private function present(Proposal $proposal): array
    {
        return [
            'id' => $proposal->uuid,
            'kind' => $proposal->proposal_type,
            'term_id' => $proposal->term_id,
            'base_revision' => $proposal->base_revision,
            'changes' => $proposal->changes,
            'status' => $proposal->status,
            'created_at' => $proposal->created_at?->toIso8601ZuluString(),
            'resolved_at' => $proposal->resolved_at?->toIso8601ZuluString(),
        ];
    }

    private function token(Request $request): ServiceAccountToken
    {
        $token = $request->attributes->get('service_token');
        abort_unless($token instanceof ServiceAccountToken, 401);

        return $token;
    }
}
