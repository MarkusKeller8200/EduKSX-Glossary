<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\ServiceAccountToken;
use App\Models\Term;
use App\Proposals\ProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $data = $request->validate([
            'term' => ['nullable', 'string', 'max:200'],
            'changes' => ['required', 'array'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $term = null;
        if (($data['term'] ?? '') !== '') {
            $term = Term::query()->published()->whereNull('merged_into_id')
                ->where(fn ($q) => $q->where('id', $data['term'])->orWhere('slug', $data['term']))->with('publishedRevision')->first();
            if ($term === null) {
                return response()->json(['message' => 'Begriff nicht gefunden oder nicht veröffentlicht.'], 404);
            }
        }
        try {
            $proposal = $this->proposals->submit($term, (array) $data['changes'], $data['reason'] ?? null, null, $token->account);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['changes' => [$e->getMessage()]]], 422);
        }

        return response()->json($this->present($proposal), 201)->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $token = $this->token($request);
        $proposal = Proposal::query()->whereKey($id)->where('service_account_id', $token->account->id)->first();
        if ($proposal === null) {
            return response()->json(['message' => 'Vorschlag nicht gefunden.'], 404);
        }

        return response()->json($this->present($proposal))->header('Cache-Control', 'no-store');
    }

    /** @return array<string, mixed> */
    private function present(Proposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'kind' => $proposal->kind,
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
