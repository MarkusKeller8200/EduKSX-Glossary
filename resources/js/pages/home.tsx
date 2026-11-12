import { Head, Link, router, usePage } from '@inertiajs/react';
import { Filter, LayoutGrid, List, Table2 } from 'lucide-react';
import { useMemo, useState, lazy, Suspense } from 'react';
import DataGrid from '@/components/data-grid';
import ExportMenu from '@/components/export-menu';
import LabelFilter from '@/components/label-filter';
import TermCard from '@/components/term-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useHandy } from '@/hooks/use-handy';
import { cn } from '@/lib/utils';
import { ListBadges, StatusBadge } from '@/components/tags';

// PDF-Dialog und Druckansicht erst beim Öffnen laden (#137)
const GlossaryPdfDialog = lazy(
    () => import('@/components/glossary-pdf-dialog'),
);

type Begriff = {
    slug: string;
    abkuerzung: string;
    bezeichnung: string;
    kurz: string;
    kategorien: string[];
    herkunft: string[];
    status: string;
    code: string;
    beschreibung: string;
    url: string;
    aliase: string[];
    schreibweisen: string[];
    gueltigAb: string | null;
    gueltigBis: string | null;
    suchbegriffe: string;
    suche: string;
};

/** Ansicht der Liste (#213): Balken (Vorgabe), Tabelle oder Kacheln; im Browser gemerkt */
type Ansicht = 'balken' | 'tabelle' | 'kacheln';
const ANSICHT_KEY = 'glossary.ansicht';

function gespeicherteAnsicht(): Ansicht {
    try {
        const v = window.localStorage.getItem(ANSICHT_KEY);

        return v === 'tabelle' || v === 'kacheln' ? v : 'balken';
    } catch {
        return 'balken';
    }
}

/**
 * Suchfeld nur am Desktop automatisch fokussieren (#235): Auf dem Handy würde sonst sofort die Tastatur aufgehen.
 */
function mitAutofokus(): boolean {
    try {
        return (
            !window.matchMedia('(pointer: coarse)').matches &&
            window.matchMedia('(min-width: 640px)').matches
        );
    } catch {
        return true;
    }
}

const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');

/** Anfangsbuchstabe für den Index (Umlaute zum Grundbuchstaben, Ziffern unter «#»). */
function initial(b: Begriff): string {
    const first = (b.abkuerzung || b.bezeichnung)
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .charAt(0)
        .toUpperCase();

    return ALPHABET.includes(first) ? first : '#';
}

/** Startseite: veröffentlichte Begriffe mit Suche, Index A–Z und Kategorien (#24, PUB-01/02). */
export default function Home({ begriffe }: { begriffe: Begriff[] }) {
    const { auth } = usePage().props;
    const [suche, setSuche] = useState('');
    const [autofokus] = useState(mitAutofokus);
    // Handy (#237): Index A–Z und Filter eingeklappt
    const [filterOffen, setFilterOffen] = useState(false);
    const handy = useHandy();
    const [pdf, setPdf] = useState(false);
    const [buchstabe, setBuchstabe] = useState<string | null>(null);
    // Filter Kategorie und Herkunft, Mehrfachauswahl (#145)
    const [gewaehlt, setGewaehlt] = useState<string[]>([]);
    const [herkunft, setHerkunft] = useState<string[]>([]);
    const [ansicht, setAnsichtState] = useState<Ansicht>(gespeicherteAnsicht);
    const setAnsicht = (v: Ansicht) => {
        setAnsichtState(v);
        try {
            window.localStorage.setItem(ANSICHT_KEY, v);
        } catch {
            // ohne Speicher: gilt bis zum Neuladen
        }
    };

    const kategorien = useMemo(
        () =>
            [...new Set(begriffe.flatMap((b) => b.kategorien))].sort((a, b) =>
                a.localeCompare(b, 'de-CH'),
            ),
        [begriffe],
    );
    const herkuenfte = useMemo(
        () =>
            [...new Set(begriffe.flatMap((b) => b.herkunft ?? []))].sort(
                (a, b) => a.localeCompare(b, 'de-CH'),
            ),
        [begriffe],
    );
    const vorhanden = useMemo(() => new Set(begriffe.map(initial)), [begriffe]);

    // Handy immer Kacheln (#246); die gewählte Ansicht bleibt für den Desktop gespeichert
    const sicht: Ansicht = handy ? 'kacheln' : ansicht;

    const aktiveFilter =
        (buchstabe === null ? 0 : 1) + gewaehlt.length + herkunft.length;

    const q = suche.trim().toLowerCase();
    const liste = begriffe.filter(
        (b) =>
            (q === '' || b.suche.includes(q)) &&
            (buchstabe === null || initial(b) === buchstabe) &&
            (gewaehlt.length === 0 ||
                b.kategorien.some((k) => gewaehlt.includes(k))) &&
            (herkunft.length === 0 ||
                b.herkunft.some((h) => herkunft.includes(h))),
    );

    const anzahl = (
        <p className="text-sm text-muted-foreground" aria-live="polite">
            {liste.length === 1 ? '1 Begriff' : `${liste.length} Begriffe`}
            {liste.length !== begriffe.length ? ` von ${begriffe.length}` : ''}
        </p>
    );

    // Export der sichtbaren Begriffe (#131): ab Recht «export», sonst ausgegraut mit Hinweis
    const export_ = (compact = false) => (
        <ExportMenu
            endpoint="/export"
            keys={liste.map((b) => b.slug)}
            allowed={auth.can.export === true}
            hint={
                auth.user
                    ? { text: 'Export ab Rolle Benutzer' }
                    : { text: 'Zum Exportieren anmelden', href: '/anmelden' }
            }
            onPdf={() => setPdf(true)}
            compact={compact}
        />
    );

    return (
        <>
            <Head title="Begriffe und Abkürzungen" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Begriffe und Abkürzungen
            </h1>
            <p className="mt-2 max-w-2xl text-muted-foreground">
                Ein öffentliches Glossar für Schweizer Volksschulen,
                Schulgemeinden und Gemeinden: Abkürzungen und Begriffe mit
                Erklärung, Quelle und Gültigkeit.
            </p>

            {/* Handy (#244, #245): ab dem Suchfeld fixiert, darunter Filter, Anzahl und Export */}
            <div className="sticky top-[var(--kopf-hoehe,0px)] z-10 -mx-4 mt-6 bg-background px-4 py-2 sm:static sm:mx-0 sm:px-0 sm:py-0">
                <div className="flex flex-wrap items-center gap-3">
                    <div className="w-full max-w-xl">
                        <label htmlFor="suche" className="sr-only">
                            Begriff suchen
                        </label>
                        <Input
                            id="suche"
                            type="search"
                            placeholder="Begriff oder Abkürzung suchen …"
                            value={suche}
                            onChange={(e) => setSuche(e.target.value)}
                            autoFocus={autofokus}
                        />
                    </div>
                    {handy ? null : export_()}
                </div>

                {handy ? (
                    <div className="mt-2 flex items-center gap-2">
                        <Button
                            variant="outline"
                            className="shrink-0"
                            onClick={() => setFilterOffen(!filterOffen)}
                            aria-expanded={filterOffen}
                            aria-controls="filter-bereich"
                        >
                            <Filter aria-hidden /> Filter
                            {aktiveFilter > 0 ? (
                                <span className="rounded-full bg-primary px-1.5 text-xs text-primary-foreground">
                                    {aktiveFilter}
                                </span>
                            ) : null}
                        </Button>
                        {anzahl}
                        <div className="ml-auto shrink-0">{export_(true)}</div>
                    </div>
                ) : null}

                <div
                    id="filter-bereich"
                    className={cn(
                        handy && 'max-h-[50dvh] overflow-y-auto',
                        handy && !filterOffen && 'hidden',
                    )}
                >
                    <nav
                        aria-label="Index A–Z"
                        className="mt-4 flex flex-wrap gap-1 text-sm"
                    >
                        {[...ALPHABET, '#'].map((l) => (
                            <button
                                key={l}
                                type="button"
                                disabled={!vorhanden.has(l)}
                                aria-pressed={buchstabe === l}
                                onClick={() =>
                                    setBuchstabe(buchstabe === l ? null : l)
                                }
                                className={cn(
                                    'min-w-8 rounded-md px-2 py-1 font-medium disabled:text-muted-foreground/50',
                                    buchstabe === l
                                        ? 'bg-primary text-primary-foreground'
                                        : 'hover:bg-accent hover:text-accent-foreground',
                                )}
                            >
                                {l}
                            </button>
                        ))}
                    </nav>

                    <div className="mt-3 grid gap-2">
                        <LabelFilter
                            list="herkunft"
                            title="Herkunft"
                            labels={herkuenfte}
                            selected={herkunft}
                            onChange={setHerkunft}
                        />
                        <LabelFilter
                            list="kategorien"
                            title="Kategorie"
                            labels={kategorien}
                            selected={gewaehlt}
                            onChange={setGewaehlt}
                        />
                    </div>
                </div>
            </div>

            {/* Desktop: Anzahl und Ansicht bleiben beim Scrollen unter der Kopfzeile (#238); Handy immer Kacheln (#246) */}
            {handy ? null : (
                <div className="sticky top-[var(--kopf-hoehe,0px)] z-10 -mx-4 mt-4 flex flex-wrap items-center justify-between gap-2 bg-background px-4 py-2">
                    {anzahl}
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        value={ansicht}
                        onValueChange={(v) => v && setAnsicht(v as Ansicht)}
                        aria-label="Ansicht"
                    >
                        <ToggleGroupItem
                            value="balken"
                            aria-label="Balken"
                            title="Balken"
                        >
                            <List aria-hidden />
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value="tabelle"
                            aria-label="Tabelle"
                            title="Tabelle"
                        >
                            <Table2 aria-hidden />
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value="kacheln"
                            aria-label="Kacheln"
                            title="Kacheln"
                        >
                            <LayoutGrid aria-hidden />
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
            )}

            {sicht === 'balken' ? (
                <ul className="mt-2 divide-y divide-border border-y border-border">
                    {liste.map((b) => (
                        <li key={b.slug}>
                            <Link
                                href={`/begriffe/${b.slug}`}
                                className="block px-1 py-3 hover:bg-accent/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                <span className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                    {b.abkuerzung ? (
                                        <span
                                            className="font-semibold"
                                            translate="no"
                                        >
                                            {b.abkuerzung}
                                        </span>
                                    ) : null}
                                    <span translate="no">{b.bezeichnung}</span>
                                    <ListBadges
                                        list="herkunft"
                                        labels={b.herkunft}
                                    />
                                    <ListBadges
                                        list="kategorien"
                                        labels={b.kategorien}
                                    />
                                    {b.status !== 'aktuell' ? (
                                        <StatusBadge status={b.status} />
                                    ) : null}
                                </span>
                                {b.kurz ? (
                                    <span className="mt-1 block text-sm text-muted-foreground">
                                        {b.kurz}
                                    </span>
                                ) : null}
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : sicht === 'kacheln' ? (
                <ul className="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {liste.map((b) => (
                        <li
                            key={b.slug}
                            className="min-w-0 cursor-pointer rounded-lg border border-border bg-card p-3 select-none hover:bg-accent/30 sm:select-text"
                            onClick={(e) => {
                                // Ganze Kachel öffnet die Details (#249); Links und Knöpfe darin behalten ihr Ziel
                                if (
                                    (e.target as HTMLElement).closest(
                                        'a, button',
                                    ) ||
                                    window.getSelection()?.toString()
                                ) {
                                    return;
                                }
                                router.visit(`/begriffe/${b.slug}`);
                            }}
                        >
                            <TermCard
                                begriff={b}
                                href={`/begriffe/${b.slug}`}
                                oeffentlich
                            />
                        </li>
                    ))}
                </ul>
            ) : (
                <div className="mt-2">
                    {/* Tabelle ohne Funktionen (#213): keine Auswahl, keine Aktionen, nur Sortieren */}
                    <DataGrid
                        id="public.begriffe"
                        caption="Begriffe"
                        rows={liste}
                        getRowId={(b: Begriff) => b.slug}
                        columns={[
                            {
                                id: 'abkuerzung',
                                header: 'Abkürzung',
                                width: 130,
                                sticky: 'left',
                                noTranslate: true,
                                sortValue: (b) => b.abkuerzung,
                                cell: (b) => (
                                    <Link
                                        href={`/begriffe/${b.slug}`}
                                        className="font-semibold text-link underline-offset-4 hover:underline"
                                    >
                                        {b.abkuerzung || '–'}
                                    </Link>
                                ),
                            },
                            {
                                id: 'bezeichnung',
                                header: 'Bezeichnung',
                                width: 240,
                                noTranslate: true,
                                sortValue: (b) => b.bezeichnung,
                                cell: (b) => b.bezeichnung,
                            },
                            {
                                id: 'kurz',
                                header: 'Kurzbeschreibung',
                                width: 360,
                                noTranslate: true,
                                sortValue: (b) => b.kurz,
                                cell: (b) => b.kurz,
                            },
                            {
                                id: 'herkunft',
                                header: 'Herkunft',
                                width: 150,
                                sortValue: (b) => b.herkunft.join(', '),
                                cell: (b) => (
                                    <ListBadges
                                        list="herkunft"
                                        labels={b.herkunft}
                                    />
                                ),
                            },
                            {
                                id: 'kategorien',
                                header: 'Kategorien',
                                width: 150,
                                sortValue: (b) => b.kategorien.join(', '),
                                cell: (b) => (
                                    <ListBadges
                                        list="kategorien"
                                        labels={b.kategorien}
                                    />
                                ),
                            },
                            {
                                id: 'status',
                                header: 'Status',
                                width: 110,
                                sortValue: (b) => b.status,
                                cell: (b) => <StatusBadge status={b.status} />,
                            },
                        ]}
                    />
                </div>
            )}

            {begriffe.length === 0 ? (
                <p className="mt-6 text-muted-foreground">
                    Noch keine Begriffe veröffentlicht.
                </p>
            ) : null}

            {pdf ? (
                <Suspense fallback={null}>
                    <GlossaryPdfDialog
                        open={pdf}
                        onOpenChange={setPdf}
                        quelle="/export?format=json"
                        gefiltert={liste.map((b) => b.slug)}
                    />
                </Suspense>
            ) : null}

            <p className="mt-8 text-sm">
                Fehlt ein Begriff?{' '}
                <Link
                    href="/vorschlaege/neu"
                    className="text-link underline underline-offset-4"
                >
                    Begriff vorschlagen
                </Link>
            </p>
        </>
    );
}
