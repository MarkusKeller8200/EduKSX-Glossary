<?php

namespace App\Http\Controllers;

use App\Glossary\PublicGlossary;
use App\Models\Term;
use App\Models\TermUserOverride;
use App\Models\User;
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
        $user = $this->user($request);
        $term = $this->term($slug, $glossary);
        $data = $request->validate([
            'abbreviation_override' => ['nullable', 'string', 'max:60'],
            'name_override' => ['nullable', 'string', 'max:255'],
            'short_description_override' => ['nullable', 'string', 'max:160'],
            'description_override' => ['nullable', 'string', 'max:20000'],
            'aliases_override' => ['nullable', 'string', 'max:1000'],
        ]);

        $values = [];
        foreach (['abbreviation_override', 'name_override', 'short_description_override', 'description_override'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $values[$field] = $value === '' ? null : $value;
        }
        $aliases = array_values(array_unique(array_filter(
            array_map(fn (string $a): string => mb_substr(trim($a), 0, 120), explode(';', (string) ($data['aliases_override'] ?? ''))),
            fn (string $a): bool => $a !== '',
        )));
        $values['aliases_override'] = $aliases === [] ? null : $aliases;

        $override = TermUserOverride::query()->firstOrNew(['term_id' => $term->id, 'user_id' => $user->id]);
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
        TermUserOverride::query()->where('term_id', $term->id)->where('user_id', $this->user($request)->id)->delete();

        return back()->with('status', 'Meine Darstellung entfernt – es gilt die gemeinsame.');
    }

    /** Nur veröffentlichte, öffentlich sichtbare Begriffe. */
    private function term(string $slug, PublicGlossary $glossary): Term
    {
        abort_if($glossary->find($slug) === null, 404);

        return Term::query()->where('slug', $slug)->firstOrFail();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
