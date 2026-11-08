<?php

namespace App\Glossary;

use App\Models\Category;
use App\Models\Organization;
use App\Models\OrganizationTermOverride;
use App\Models\Source;
use App\Models\Term;
use App\Models\TermRevision;
use App\Models\TermUserOverride;
use App\Models\User;
use App\Organizations\OrganizationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Öffentliche Sicht auf das Glossary (#24): ausschliesslich veröffentlichte Revisionen, die im Abbild aktiv sind – nie der
 * Arbeitsstand. Nicht öffentlich: redaktionelle Notiz, Altwerte, Verlauf.
 *
 * Persönliche Darstellung (DEC-020): Nur wenn ein Konto übergeben wird (angemeldet), gelten dessen Anpassungen
 * (TermUserOverride) für Liste und Detail. Solche Antworten dürfen nicht zwischengespeichert werden
 * (PageController setzt «Cache-Control: private, no-store»).
 *
 * Darstellung der Organisation (#148, DEC-025): Aktive Mitglieder sehen die Anpassungen ihrer Organisation.
 * Reihenfolge: persönlich vor Organisation vor gemeinsam; Schreibweisen werden ergänzt.
 */
final class PublicGlossary
{
    /** Positivliste der Felder auf der Detailseite (#43, DEC-018); ohne Notiz, «Intern genutzt in», Verlauf. */
    public const PUBLIC_DETAIL_FIELDS = [
        'slug', 'abkuerzung', 'bezeichnung', 'abkuerzung_offiziell', 'bezeichnung_offiziell', 'aliase', 'kurz',
        'beschreibung', 'url', 'kategorien', 'herkunft', 'status', 'gueltig_ab', 'gueltig_bis', 'revision',
        'veroeffentlicht', 'andere', 'mehrdeutig', 'verlauf',
    ];

    /** Zusätzliche Felder nur für das angemeldete Konto selbst (nie öffentlich). */
    public const PERSONAL_FIELDS = ['persoenlich', 'gemeinsam', 'meine', 'organisation'];

    /**
     * Kurzform für Liste und Suche.
     *
     * @return list<array{slug: string, abkuerzung: string, bezeichnung: string, kurz: string, kategorien: list<string>, herkunft: list<string>, status: string, suche: string, persoenlich?: bool, organisation?: bool}>
     */
    public function list(?User $user = null): array
    {
        $organization = app(OrganizationService::class)->activeOrganizationOf($user);
        $user = $this->personalFor($user);
        $categories = $this->labels(Category::class);
        $sources = $this->labels(Source::class);
        $overrides = $this->overrides($user);
        $orgOverrides = $this->organizationOverrides($organization);

        $list = $this->published()
            ->map(function (array $s) use ($categories, $sources, $overrides, $orgOverrides): array {
                $abk = (string) ($s['official_abbreviation'] ?? '');
                $name = (string) ($s['official_name'] ?? '');
                $kurz = (string) ($s['short_description'] ?? '');
                $o = $overrides[(string) $s['id']] ?? null;
                $g = $orgOverrides[(string) $s['id']] ?? null;

                $item = [
                    'slug' => (string) $s['slug'],
                    'abkuerzung' => $o->abbreviation_override ?? $g->abbreviation_override ?? $abk,
                    'bezeichnung' => $o->name_override ?? $g->name_override ?? $name,
                    'kurz' => $o->short_description_override ?? $g->short_description_override ?? $kurz,
                    'kategorien' => $this->mapLabels($categories, $s['categories']),
                    // Filter nach Herkunft auf der Startseite (#145)
                    'herkunft' => $this->mapLabels($sources, $s['sources'] ?? []),
                    'status' => Term::STATUS[(string) $s['lifecycle_status']] ?? (string) $s['lifecycle_status'],
                    'suche' => mb_strtolower(implode(' ', array_filter([
                        $abk, $name, $kurz, implode(' ', (array) ($s['official_aliases'] ?? [])),
                        implode(' ', (array) ($s['alternative_aliases'] ?? [])), $s['search_terms'] ?? null,
                        $o?->abbreviation_override, $o?->name_override, $o?->short_description_override,
                        implode(' ', $o->aliases_override ?? []),
                        $g?->abbreviation_override, $g?->name_override, $g?->short_description_override,
                        implode(' ', $g->aliases_override ?? []),
                    ]))),
                ];
                if ($o !== null) {
                    $item['persoenlich'] = true;
                }
                if ($g !== null) {
                    $item['organisation'] = true;
                }

                return $item;
            })
            ->sortBy(fn (array $t): string => mb_strtolower($t['abkuerzung'] ?: $t['bezeichnung']))
            ->values()
            ->all();

        return array_values($list);
    }

    /**
     * Ein veröffentlichter Begriff mit allen öffentlichen Angaben; null, wenn nicht veröffentlicht oder inaktiv. Mit
     * Konto zusätzlich dessen persönliche Darstellung (PERSONAL_FIELDS).
     *
     * @return array<string, mixed>|null
     */
    public function find(string $slug, ?User $user = null): ?array
    {
        $organizations = app(OrganizationService::class);
        $organization = $organizations->activeOrganizationOf($user);
        $orgAdmin = $user !== null && $organization !== null && $organizations->isAdmin($user, $organization);
        $user = $this->personalFor($user);
        $term = Term::query()->published()->where('slug', $slug)->with('publishedRevision')->first();
        $revision = $term?->publishedRevision;
        if ($term === null || $revision === null || ($revision->snapshot['is_active'] ?? true) !== true) {
            return null;
        }
        $s = $revision->snapshot;
        $categories = $this->labels(Category::class);
        $sources = Source::query()->whereIn('key', array_map('strval', (array) $s['sources']))->orderBy('sort_order')->get();
        $abk = (string) ($s['official_abbreviation'] ?? '');

        // Andere Bedeutungen derselben Abkürzung (mehrdeutig, Regel aus dem Intranet) – immer gemeinsame Werte
        $others = $abk === '' ? [] : array_values(array_filter(
            $this->list(),
            fn (array $t): bool => $t['slug'] !== $s['slug'] && GlossaryDisplay::normalize($t['abkuerzung']) === GlossaryDisplay::normalize($abk),
        ));

        $detail = [
            'slug' => $s['slug'],
            'abkuerzung' => $abk,
            'bezeichnung' => (string) ($s['official_name'] ?? ''),
            'abkuerzung_offiziell' => $s['official_abbreviation'] ?? null,
            'bezeichnung_offiziell' => $s['official_name'] ?? null,
            'aliase' => array_values(array_unique(array_filter([
                ...(array) ($s['official_aliases'] ?? []), ...(array) ($s['alternative_aliases'] ?? []),
            ]))),
            'kurz' => $s['short_description'],
            'beschreibung' => $s['description'],
            'url' => $s['url'],
            'kategorien' => $this->mapLabels($categories, $s['categories']),
            'herkunft' => $sources->map(fn (Source $source): array => [
                'label' => $source->displayLabel(),
                'name' => $source->name,
                'url' => $source->url,
            ])->all(),
            'status' => Term::STATUS[(string) $s['lifecycle_status']] ?? (string) $s['lifecycle_status'],
            'gueltig_ab' => $s['valid_from'],
            'gueltig_bis' => $s['valid_until'],
            'revision' => $revision->revision,
            'veroeffentlicht' => $revision->published_at?->toDateString(),
            'andere' => array_map(fn (array $t): array => ['slug' => $t['slug'], 'bezeichnung' => $t['bezeichnung']], $others),
            'mehrdeutig' => (new GlossaryDisplay($this->published()))->isAmbiguous($s),
            // Verlauf (#68): nur veröffentlichte Revisionen mit Datum – ohne Personen, Entwürfe oder Inhalte
            'verlauf' => $term->revisions()->whereNotNull('published_at')->reorder('revision', 'desc')->get(['revision', 'published_at'])
                ->map(fn (TermRevision $r): array => ['revision' => $r->revision, 'am' => $r->published_at?->toDateString()])
                ->values()->all(),
        ];
        $detail = array_intersect_key($detail, array_flip(self::PUBLIC_DETAIL_FIELDS));

        if ($user === null && $organization === null) {
            return $detail;
        }

        $detail['gemeinsam'] = ['abkuerzung' => $detail['abkuerzung'], 'bezeichnung' => $detail['bezeichnung'], 'kurz' => $detail['kurz'], 'beschreibung' => $detail['beschreibung']];

        // Darstellung der Organisation (#148): für alle aktiven Mitglieder, bearbeiten nur Organisations-Admins
        if ($organization !== null) {
            $g = $this->organizationOverrides($organization, (string) $s['id'])[(string) $s['id']] ?? null;
            $detail['organisation'] = [
                'name' => $organization->name,
                'gesetzt' => $g !== null,
                'bearbeiten' => $orgAdmin,
                'werte' => $orgAdmin ? self::formValues($g) : null,
            ];
            $detail = self::apply($detail, $g);
        }

        // Persönliche Darstellung: nur für dieses Konto, gemeinsame Werte bleiben sichtbar
        if ($user !== null) {
            $o = $this->overrides($user, (string) $s['id'])[(string) $s['id']] ?? null;
            $detail['persoenlich'] = $o !== null;
            $detail['meine'] = self::formValues($o);
            $detail = self::apply($detail, $o);
        }

        return $detail;
    }

    /**
     * Anpassungen über die Detailwerte legen (leere Felder behalten den bisherigen Wert).
     *
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private static function apply(array $detail, TermUserOverride|OrganizationTermOverride|null $o): array
    {
        if ($o === null) {
            return $detail;
        }
        $detail['abkuerzung'] = $o->abbreviation_override ?? $detail['abkuerzung'];
        $detail['bezeichnung'] = $o->name_override ?? $detail['bezeichnung'];
        $detail['kurz'] = $o->short_description_override ?? $detail['kurz'];
        $detail['beschreibung'] = $o->description_override ?? $detail['beschreibung'];
        $detail['aliase'] = array_values(array_unique([...(array) $detail['aliase'], ...($o->aliases_override ?? [])]));

        return $detail;
    }

    /**
     * Werte für das Formular (leere Zeichenketten statt NULL, Schreibweisen mit «;»).
     *
     * @return array<string, string>
     */
    public static function formValues(TermUserOverride|OrganizationTermOverride|null $o): array
    {
        return [
            'abbreviation_override' => (string) $o?->abbreviation_override,
            'name_override' => (string) $o?->name_override,
            'short_description_override' => (string) $o?->short_description_override,
            'description_override' => (string) $o?->description_override,
            'aliases_override' => implode('; ', $o->aliases_override ?? []),
        ];
    }

    /**
     * Darstellungen einer Organisation, je Begriff-ID.
     *
     * @return array<string, OrganizationTermOverride>
     */
    public function organizationOverrides(?Organization $organization, ?string $termId = null): array
    {
        if ($organization === null) {
            return [];
        }
        $query = OrganizationTermOverride::query()->where('organization_id', $organization->id);
        if ($termId !== null) {
            $query->where('term_id', $termId);
        }

        return $query->get()->keyBy('term_id')->all();
    }

    /**
     * Konto, dessen persönliche Darstellung gilt: nur mit Recht «personal-display» (#114). Ohne Recht (z. B. Gast)
     * die gemeinsame Sicht; gespeicherte Anpassungen bleiben erhalten.
     */
    private function personalFor(?User $user): ?User
    {
        return $user !== null && Gate::forUser($user)->allows('personal-display') ? $user : null;
    }

    /**
     * Persönliche Darstellungen eines Kontos, je Begriff-ID (nur Datensätze dieses Kontos).
     *
     * @return array<string, TermUserOverride>
     */
    private function overrides(?User $user, ?string $termId = null): array
    {
        if ($user === null) {
            return [];
        }
        $query = TermUserOverride::query()->where('user_id', $user->id);
        if ($termId !== null) {
            $query->where('term_id', $termId);
        }

        return $query->get()->keyBy('term_id')->all();
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<string>
     */
    private function mapLabels(array $labels, mixed $keys): array
    {
        $out = [];
        foreach ((array) $keys as $key) {
            $out[] = $labels[(string) $key] ?? (string) $key;
        }

        return $out;
    }

    /**
     * Abbilder der veröffentlichten Revisionen aktiver Begriffe.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function published(): Collection
    {
        // Abbilder je Datenstand zwischengespeichert (#137)
        /** @var list<array<string, mixed>> $snapshots */
        $snapshots = app(PublicCache::class)->remember('snapshots', function (): array {
            $snapshots = [];
            foreach (Term::query()->published()->with('publishedRevision')->get() as $term) {
                $snapshot = $term->publishedRevision?->snapshot;
                if ($snapshot !== null && ($snapshot['is_active'] ?? true) === true) {
                    $snapshots[] = $snapshot;
                }
            }

            return $snapshots;
        });

        return new Collection($snapshots);
    }

    /**
     * Schlüssel → Label einer Liste.
     *
     * @param  class-string<Category|Source>  $model
     * @return array<string, string>
     */
    private function labels(string $model): array
    {
        $labels = [];
        foreach ($model::query()->orderBy('sort_order')->get() as $item) {
            $labels[$item->key] = $item->displayLabel();
        }

        return $labels;
    }
}
