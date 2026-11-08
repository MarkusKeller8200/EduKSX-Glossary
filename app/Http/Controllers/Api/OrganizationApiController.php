<?php

namespace App\Http\Controllers\Api;

use App\Glossary\ApiPresenter;
use App\Glossary\PublicGlossary;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationTermOverride;
use App\Models\ServiceAccountToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Begriffe in der Darstellung einer Organisation (#148, DEC-025): nur mit Token eines Servicekontos der Organisation
 * (Scope «read-org»). Je Begriff die gemeinsamen Werte («term», wie /terms) und die Anpassungen der Organisation
 * («organization_display», sonst null). Nie zwischengespeichert.
 */
class OrganizationApiController extends Controller
{
    public function __construct(private readonly ApiPresenter $presenter, private readonly PublicGlossary $glossary) {}

    /** GET /api/v1/org/terms – alle Begriffe; mit «customized=1» nur angepasste. */
    public function terms(Request $request): JsonResponse
    {
        $organization = $this->organization($request);
        $overrides = $this->glossary->organizationOverrides($organization);
        $onlyCustomized = $request->boolean('customized');

        $data = [];
        foreach ($this->presenter->terms() as $term) {
            $override = $overrides[(string) $term['id']] ?? null;
            if ($onlyCustomized && $override === null) {
                continue;
            }
            $data[] = ['term' => $term, 'organization_display' => self::display($override)];
        }

        return $this->respond([
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'data' => $data,
            'meta' => ['total' => count($data), 'customized' => count($overrides), 'release' => $this->presenter->release()['release']],
        ]);
    }

    /** GET /api/v1/org/terms/{idOrSlug} */
    public function term(Request $request, string $idOrSlug): JsonResponse
    {
        $organization = $this->organization($request);
        $term = $this->presenter->find($idOrSlug);
        abort_if($term === null, 404, 'Begriff nicht gefunden oder nicht veröffentlicht.');
        $override = $this->glossary->organizationOverrides($organization, (string) $term['id'])[(string) $term['id']] ?? null;

        return $this->respond([
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'data' => ['term' => $term, 'organization_display' => self::display($override)],
        ]);
    }

    /**
     * @return array{abbreviation: string|null, name: string|null, short_description: string|null, description: string|null, aliases: list<string>, updated_at: string|null}|null
     */
    private static function display(?OrganizationTermOverride $o): ?array
    {
        return $o === null ? null : [
            'abbreviation' => $o->abbreviation_override,
            'name' => $o->name_override,
            'short_description' => $o->short_description_override,
            'description' => $o->description_override,
            'aliases' => array_values($o->aliases_override ?? []),
            'updated_at' => $o->updated_at?->toIso8601ZuluString(),
        ];
    }

    private function organization(Request $request): Organization
    {
        $token = $request->attributes->get('service_token');
        abort_unless($token instanceof ServiceAccountToken, 401);
        $organization = $token->account->organization;
        // Servicekonto ohne Organisation (z. B. Organisation gelöscht) → kein Zugriff
        abort_if($organization === null, 403, 'Das Servicekonto gehört zu keiner Organisation.');

        return $organization;
    }

    /** @param  array<string, mixed>  $body */
    private function respond(array $body): JsonResponse
    {
        return response()->json($body, 200, ['Cache-Control' => 'private, no-store'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
