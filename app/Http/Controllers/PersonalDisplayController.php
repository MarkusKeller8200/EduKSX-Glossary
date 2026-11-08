<?php

namespace App\Http\Controllers;

use App\Glossary\DisplayInput;
use App\Glossary\PublicGlossary;
use App\Models\Term;
use App\Models\TermUserOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «Meine Darstellung» (DEC-020, #82): persönliche Abkürzung, Bezeichnung, Beschreibungen und Aliase eines
 * veröffentlichten Begriffs. Nur für das angemeldete Konto selbst; keine Moderation, keine Revision, nie öffentlich.
 */
class PersonalDisplayController extends Controller
{
    public function update(Request $request, string $slug, PublicGlossary $glossary): RedirectResponse
    {
        $user = $this->currentUser($request);
        $term = $this->term($slug, $glossary);
        $values = DisplayInput::from($request);

        $override = TermUserOverride::query()->where('term_id', $term->id)->where('user_id', $user->id)->first()
            ?? new TermUserOverride;
        $override->term_id = $term->id;
        $override->user_id = $user->id;
        $override->fill($values);
        if ($override->isEmpty()) {
            if ($override->exists) {
                $override->delete();
            }

            return back()->with('status', 'Keine persönliche Darstellung – es gilt die gemeinsame.');
        }
        $override->save();

        return back()->with('status', 'Meine Darstellung gespeichert – nur für dich sichtbar.');
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
