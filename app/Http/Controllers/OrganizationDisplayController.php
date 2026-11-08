<?php

namespace App\Http\Controllers;

use App\Access\AccessDenied;
use App\Glossary\DisplayInput;
use App\Glossary\PublicGlossary;
use App\Models\Organization;
use App\Models\OrganizationTermOverride;
use App\Models\Term;
use App\Models\TermUserOverride;
use App\Models\User;
use App\Organizations\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Darstellung eines Begriffs für die eigene Organisation (#148): setzen nur Organisations-Admins, sichtbar für alle
 * aktiven Mitglieder. Nie öffentlich, nicht in Revisionen; über die API nur mit Token der Organisation.
 */
class OrganizationDisplayController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations) {}

    public function update(Request $request, string $slug, PublicGlossary $glossary): RedirectResponse
    {
        $user = $this->currentUser($request);
        $term = $this->term($slug, $glossary);
        $values = DisplayInput::from($request);
        try {
            $override = $this->organizations->setDisplay($user, $this->organizationOf($user), $term, $values);
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages(['organisation' => $e->getMessage()]);
        }

        return back()->with('status', $override === null
            ? 'Keine Darstellung der Organisation – es gilt die gemeinsame.'
            : 'Darstellung der Organisation gespeichert – sichtbar für alle Mitglieder.');
    }

    /**
     * Meine Darstellung als Darstellung der Organisation übernehmen (#165): ersetzt die bisherige Darstellung der
     * Organisation für diesen Begriff; auf Wunsch (Vorgabe) wird die eigene danach gelöscht.
     */
    public function fromPersonal(Request $request, string $slug, PublicGlossary $glossary): RedirectResponse
    {
        $user = $this->currentUser($request);
        $term = $this->term($slug, $glossary);
        $delete = $request->boolean('loeschen', true);
        $personal = TermUserOverride::query()->where('term_id', $term->id)->where('user_id', $user->id)->first();
        if ($personal === null || $personal->isEmpty()) {
            throw ValidationException::withMessages(['organisation' => 'Keine eigene Darstellung zum Übernehmen.']);
        }
        $values = [];
        foreach (TermUserOverride::FIELDS as $field) {
            $value = $personal->getAttribute($field);
            $values[$field] = is_array($value) ? array_values(array_map('strval', $value)) : ($value === null ? null : (string) $value);
        }
        try {
            $this->organizations->setDisplay($user, $this->organizationOf($user), $term, $values);
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages(['organisation' => $e->getMessage()]);
        }
        if ($delete) {
            $personal->delete();
        }

        return back()->with('status', $delete
            ? 'Als Darstellung der Organisation übernommen; Meine Darstellung gelöscht.'
            : 'Als Darstellung der Organisation übernommen; Meine Darstellung bleibt.');
    }

    public function destroy(Request $request, string $slug, PublicGlossary $glossary): RedirectResponse
    {
        $user = $this->currentUser($request);
        $term = $this->term($slug, $glossary);
        try {
            $this->organizations->setDisplay($user, $this->organizationOf($user), $term, array_fill_keys(OrganizationTermOverride::FIELDS, null));
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages(['organisation' => $e->getMessage()]);
        }

        return back()->with('status', 'Darstellung der Organisation entfernt – es gilt die gemeinsame.');
    }

    private function organizationOf(User $user): Organization
    {
        return $this->organizations->activeOrganizationOf($user) ?? abort(403);
    }

    /** Nur veröffentlichte, öffentlich sichtbare Begriffe. */
    private function term(string $slug, PublicGlossary $glossary): Term
    {
        abort_if($glossary->find($slug) === null, 404);

        return Term::query()->where('slug', $slug)->firstOrFail();
    }
}
