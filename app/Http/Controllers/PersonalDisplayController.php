<?php

namespace App\Http\Controllers;

use App\Glossary\DisplayInput;
use App\Glossary\PublicGlossary;
use App\Models\Term;
use App\Models\TermUserOverride;
use App\Organizations\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * «Meine Darstellung» (DEC-020, #82): persönliche Abkürzung, Bezeichnung, Beschreibungen und Aliase eines
 * veröffentlichten Begriffs. Nur für das angemeldete Konto selbst; keine Moderation, keine Revision, nie öffentlich.
 * Seit #148 nur als Mitglied einer Organisation; gilt nach Freigabe durch einen Organisations-Admin (eigene Texte
 * der Organisations-Admins sofort). Jede Änderung braucht eine neue Freigabe.
 */
class PersonalDisplayController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations) {}

    public function update(Request $request, string $slug, PublicGlossary $glossary): RedirectResponse
    {
        $user = $this->currentUser($request);
        $term = $this->term($slug, $glossary);
        // nur für aktive Mitglieder einer Organisation (#148)
        $organization = $this->organizations->activeOrganizationOf($user);
        if ($organization === null) {
            throw ValidationException::withMessages(['organisation' => 'Meine Darstellung ist als Mitglied einer Organisation möglich.']);
        }
        $values = DisplayInput::from($request);

        $override = TermUserOverride::query()->where('term_id', $term->id)->where('user_id', $user->id)->first()
            ?? new TermUserOverride;
        $override->term_id = $term->id;
        $override->user_id = $user->id;
        $override->fill($values);
        // eigene Texte der Organisations-Admins gelten sofort, sonst nach Freigabe
        $admin = $this->organizations->isAdmin($user, $organization);
        $override->forceFill($admin
            ? ['approved_organization_id' => $organization->id, 'approved_by_id' => $user->id, 'approved_at' => now()]
            : ['approved_organization_id' => null, 'approved_by_id' => null, 'approved_at' => null]);
        if ($override->isEmpty()) {
            if ($override->exists) {
                $override->delete();
            }

            return back()->with('status', 'Keine persönliche Darstellung – es gilt die gemeinsame.');
        }
        $override->save();

        return back()->with('status', $admin
            ? 'Meine Darstellung gespeichert – nur für dich sichtbar.'
            : 'Meine Darstellung gespeichert – sie gilt, sobald ein Organisations-Admin sie freigibt.');
    }

    public function destroy(Request $request, string $slug, PublicGlossary $glossary): RedirectResponse
    {
        $term = $this->term($slug, $glossary);
        TermUserOverride::query()->where('term_id', $term->id)->where('user_id', $this->currentUser($request)->id)->delete();

        return back()->with('status', 'Meine Darstellung entfernt – es gilt die gemeinsame.');
    }

    /** Nur veröffentlichte, öffentlich sichtbare Begriffe. */
    private function term(string $slug, PublicGlossary $glossary): Term
    {
        abort_if($glossary->find($slug) === null, 404);

        return Term::query()->where('slug', $slug)->firstOrFail();
    }
}
