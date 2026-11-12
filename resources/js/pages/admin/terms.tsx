import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useMemo, useState, type KeyboardEvent, lazy, Suspense } from 'react';
import {
    ListBadges,
    RevisionBadges,
    StateBadge,
    StatusBadge,
} from '@/components/tags';
import DataGrid, { type GridSort } from '@/components/data-grid';
import ExportMenu from '@/components/export-menu';
import IconAction from '@/components/icon-action';
import LabelFilter from '@/components/label-filter';
import TermCard, { type TermCardData } from '@/components/term-card';
import ViewMenu, { type ViewLayout } from '@/components/view-menu';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { can } from '@/lib/access';

// PDF-Dialog und Druckansicht erst beim Öffnen laden (#137)
const GlossaryPdfDialog = lazy(
    () => import('@/components/glossary-pdf-dialog'),
);

type Begriff = TermCardData & {
    id: string;
    aktiv: boolean;
    revision: number;
    veroeffentlicht: number | null;
    entwurf: boolean;
    angelegt: string | null;
    geaendert: string | null;
};

type Feld = 'official_abbreviation' | 'official_name' | 'short_description';
type Gruppierung =
    | ''
    | 'kategorie'
    | 'status'
    | 'veroeffentlichung'
    | 'angelegt'
    | 'geaendert';

const GRUPPEN: Record<Gruppierung, string> = {
    '': 'keine',
    kategorie: 'Kategorie',
    status: 'Status',
    veroeffentlichung: 'Veröffentlichung',
    angelegt: 'Erstelldatum',
    geaendert: 'Letzte Änderung',
};

/**
 * Zeitraum für die Gruppierung nach Datum (#104): heute, diese Woche, dieser Monat, sonst Monat und Jahr. Der
 * Sortierschlüssel ordnet die Gruppen von neu nach alt.
 */
function zeitraum(iso: string | null): { titel: string; key: string } {
    if (!iso) {
        return { titel: 'ohne Datum', key: '0' };
    }
    const d = new Date(iso);
    const jetzt = new Date();
    const tag = (x: Date) =>
        new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime();
    const heute = tag(jetzt);
    const montag = heute - ((jetzt.getDay() + 6) % 7) * 86400000;
    if (tag(d) === heute) {
        return { titel: 'heute', key: '9-heute' };
    }
    if (tag(d) >= montag) {
        return { titel: 'diese Woche', key: '8-woche' };
    }
    if (
        d.getFullYear() === jetzt.getFullYear() &&
        d.getMonth() === jetzt.getMonth()
    ) {
        return { titel: 'dieser Monat', key: '7-monat' };
    }
    const monat = d.toLocaleDateString('de-CH', {
        month: 'long',
        year: 'numeric',
    });

    return {
        titel: monat,
        key: `1-${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
    };
}

/**
 * Begriffe (Arbeitsstand) als Redaktionstabelle (#67): filtern, gruppieren, Abkürzung, Bezeichnung und
 * Kurzbeschreibung direkt in der Zelle ändern (neue Revision als Entwurf, gleiche Regeln wie das Formular).
 */
export default function Terms({
    begriffe,
    offiziellErlaubt,
}: {
    begriffe: Begriff[];
    offiziellErlaubt: boolean;
}) {
    const { auth } = usePage().props;
    const darfVeroeffentlichen = can(auth, 'publish');
    const darfImport = can(auth, 'import');
    const darfExport = can(auth, 'export');
    const [pdf, setPdf] = useState(false);
    // sichtbare Zeilen in angezeigter Reihenfolge (Suche, Gruppen, Sortierung) für den Export (#131)
    const [sichtbar, setSichtbar] = useState<string[]>([]);
    const [suche, setSuche] = useState('');
    // Ansicht (#143): Darstellung, Gruppierung und Sortierung in einem Element, im Browser gemerkt
    const [ansicht, setAnsicht] = useState<Ansicht>(ladeAnsicht);
    const gruppierung = ansicht.gruppierung;
    const aendern = (teil: Partial<Ansicht>) =>
        setAnsicht((alt) => {
            const neu = { ...alt, ...teil };
            speichereAnsicht(neu);

            return neu;
        });
    const [resetToken, setResetToken] = useState(0);
    // Filter Kategorie und Herkunft (#145)
    const [kategorien, setKategorien] = useState<string[]>([]);
    const [herkunft, setHerkunft] = useState<string[]>([]);
    const q = suche.trim().toLowerCase();
    const liste = begriffe.filter(
        (b) =>
            (!q ||
                `${b.abkuerzung} ${b.bezeichnung} ${b.kurz} ${b.kategorien.join(' ')}`
                    .toLowerCase()
                    .includes(q)) &&
            (kategorien.length === 0 ||
                b.kategorien.some((k) => kategorien.includes(k))) &&
            (herkunft.length === 0 ||
                b.herkunft.some((h) => herkunft.includes(h))),
    );
    const alleKategorien = useMemo(
        () => labelsAus(begriffe.flatMap((b) => b.kategorien)),
        [begriffe],
    );
    const alleHerkunft = useMemo(
        () => labelsAus(begriffe.flatMap((b) => b.herkunft)),
        [begriffe],
    );
    const gruppen = useMemo(
        () => gruppieren(liste, gruppierung),
        [liste, gruppierung],
    );
    const [auswahl, setAuswahl] = useState<Set<string>>(new Set());
    const [sending, setSending] = useState(false);
    const alleGewaehlt =
        liste.length > 0 && liste.every((b) => auswahl.has(b.id));
    const umschalten = (id: string, an: boolean) =>
        setAuswahl((alt) => {
            const neu = new Set(alt);
            if (an) {
                neu.add(id);
            } else {
                neu.delete(id);
            }
            return neu;
        });
    const alleUmschalten = (an: boolean) =>
        setAuswahl((alt) => {
            const neu = new Set(alt);
            liste.forEach((b) => (an ? neu.add(b.id) : neu.delete(b.id)));
            return neu;
        });
    const entwuerfeWaehlen = () =>
        setAuswahl(new Set(liste.filter((b) => b.entwurf).map((b) => b.id)));
    const veroeffentlichen = () => {
        setSending(true);
        router.post(
            '/admin/begriffe/veroeffentlichen',
            { ids: [...auswahl] },
            {
                preserveScroll: true,
                onSuccess: () => setAuswahl(new Set()),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <>
            <Head title="Begriffe" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <h1 className="text-3xl font-semibold tracking-tight">
                    Begriffe
                </h1>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <Link href="/admin/doppelte">Doppelte prüfen</Link>
                    </Button>
                    <ExportMenu
                        endpoint="/admin/export"
                        keys={sichtbar}
                        allowed={darfExport}
                        hint={{
                            text: 'Export braucht das Recht «Exportieren»',
                        }}
                        onPdf={() => setPdf(true)}
                    />
                    {pdf ? (
                        <Suspense fallback={null}>
                            <GlossaryPdfDialog
                                open={pdf}
                                onOpenChange={setPdf}
                                quelle="/admin/export?format=json"
                                titel="Glossary – Arbeitsstand"
                                gefiltert={sichtbar}
                                ausgewaehlt={[...auswahl]}
                            />
                        </Suspense>
                    ) : null}
                    {darfImport ? (
                        <Button asChild variant="outline">
                            <Link href="/admin/import">Excel-Import</Link>
                        </Button>
                    ) : null}
                    <Button asChild>
                        <Link href="/admin/begriffe/neu">Neuer Begriff</Link>
                    </Button>
                </div>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">
                {begriffe.length} Begriffe, davon{' '}
                {begriffe.filter((b) => b.veroeffentlicht).length}{' '}
                veröffentlicht. Neue und geänderte Begriffe sind Entwürfe, bis
                sie freigegeben werden. Abkürzung, Bezeichnung und
                Kurzbeschreibung lassen sich direkt in der Zelle ändern (Klick
                bzw. Enter); jede Änderung ergibt eine neue Revision.
                {offiziellErlaubt
                    ? ''
                    : ' Offizielle Abkürzung und Bezeichnung nur, wenn sie noch leer sind.'}
            </p>

            <div className="mt-6 flex flex-wrap items-end gap-3">
                <div className="w-full max-w-sm">
                    <label htmlFor="suche" className="sr-only">
                        Begriffe filtern
                    </label>
                    <Input
                        id="suche"
                        type="search"
                        placeholder="Filtern …"
                        value={suche}
                        onChange={(e) => setSuche(e.target.value)}
                    />
                </div>
                <ViewMenu
                    layout={ansicht.layout}
                    onLayout={(layout) => aendern({ layout })}
                    groups={(Object.keys(GRUPPEN) as Gruppierung[]).map(
                        (g) => ({
                            value: g,
                            label: g === '' ? 'keine' : GRUPPEN[g],
                        }),
                    )}
                    group={gruppierung}
                    onGroup={(g) => aendern({ gruppierung: g as Gruppierung })}
                    sorts={SORTIERUNG}
                    sort={ansicht.sort}
                    onSort={(sort) => aendern({ sort })}
                    changed={
                        JSON.stringify(ansicht) !== JSON.stringify(VORGABE) ||
                        suche !== '' ||
                        kategorien.length > 0 ||
                        herkunft.length > 0
                    }
                    onReset={() => {
                        aendern(VORGABE);
                        setSuche('');
                        setKategorien([]);
                        setHerkunft([]);
                        setResetToken((t) => t + 1);
                    }}
                />
                {darfVeroeffentlichen ? (
                    <>
                        <Button variant="outline" onClick={entwuerfeWaehlen}>
                            Alle Entwürfe auswählen
                        </Button>
                        <Button
                            onClick={veroeffentlichen}
                            disabled={auswahl.size === 0 || sending}
                        >
                            Ausgewählte veröffentlichen ({auswahl.size})
                        </Button>
                    </>
                ) : null}
            </div>

            <div className="mt-3 grid gap-2">
                <LabelFilter
                    list="herkunft"
                    title="Herkunft"
                    labels={alleHerkunft}
                    selected={herkunft}
                    onChange={setHerkunft}
                />
                <LabelFilter
                    list="kategorien"
                    title="Kategorie"
                    labels={alleKategorien}
                    selected={kategorien}
                    onChange={setKategorien}
                />
            </div>

            <div className="mt-4">
                <DataGrid
                    id="admin.terms"
                    caption="Begriffe"
                    getRowId={(b: Begriff) => b.id}
                    sort={ansicht.sort}
                    onSortChange={(sort) => aendern({ sort })}
                    layout={ansicht.layout}
                    resetToken={resetToken}
                    renderCard={(b) => (
                        <TermCard
                            begriff={b}
                            href={`/admin/begriffe/${b.id}`}
                        />
                    )}
                    renderBar={(b) => <Balken begriff={b} />}
                    onVisibleRows={(rows) =>
                        setSichtbar([...new Set(rows.map((b) => b.id))])
                    }
                    groups={gruppen.map((g) => ({
                        key: g.titel,
                        title: g.titel,
                        rows: g.begriffe,
                    }))}
                    empty={
                        begriffe.length === 0
                            ? 'Noch keine Begriffe – mit dem Excel-Import übernehmen.'
                            : 'Keine Treffer.'
                    }
                    columns={[
                        {
                            id: 'auswahl',
                            label: 'Auswahl',
                            header: (
                                <Checkbox
                                    aria-label="Alle gefilterten auswählen"
                                    checked={alleGewaehlt}
                                    onCheckedChange={(v) =>
                                        alleUmschalten(v === true)
                                    }
                                />
                            ),
                            width: 44,
                            fixed: true,
                            sticky: 'left',
                            cell: (b) => (
                                <Checkbox
                                    aria-label={`${b.abkuerzung || b.bezeichnung} auswählen`}
                                    checked={auswahl.has(b.id)}
                                    onCheckedChange={(v) =>
                                        umschalten(b.id, v === true)
                                    }
                                />
                            ),
                        },
                        {
                            id: 'abkuerzung',
                            header: 'Abkürzung',
                            width: 140,
                            sticky: 'left',
                            noTranslate: true,
                            className: 'font-medium',
                            sortValue: (b) => b.abkuerzung || b.bezeichnung,
                            cell: (b) => (
                                <Zelle
                                    begriff={b}
                                    feld="official_abbreviation"
                                    wert={b.abkuerzung}
                                    gesperrt={
                                        !offiziellErlaubt && b.abkuerzung !== ''
                                    }
                                />
                            ),
                        },
                        {
                            id: 'bezeichnung',
                            header: 'Bezeichnung',
                            width: 240,
                            noTranslate: true,
                            sortValue: (b) => b.bezeichnung,
                            cell: (b) => (
                                <>
                                    <Zelle
                                        begriff={b}
                                        feld="official_name"
                                        wert={b.bezeichnung}
                                        gesperrt={
                                            !offiziellErlaubt &&
                                            b.bezeichnung !== ''
                                        }
                                    />
                                    {!b.aktiv ? (
                                        <span className="mt-1 block">
                                            <StateBadge state="inactive">
                                                inaktiv
                                            </StateBadge>
                                        </span>
                                    ) : null}
                                </>
                            ),
                        },
                        {
                            id: 'kurz',
                            header: 'Kurzbeschreibung',
                            width: 320,
                            sortValue: (b) => b.kurz,
                            cell: (b) => (
                                <Zelle
                                    begriff={b}
                                    feld="short_description"
                                    wert={b.kurz}
                                    max={160}
                                />
                            ),
                        },
                        {
                            id: 'herkunft',
                            header: 'Herkunft',
                            width: 140,
                            sortValue: (b) => b.herkunft.join(', '),
                            cell: (b) => (
                                <div className="flex flex-wrap gap-1">
                                    <ListBadges
                                        list="herkunft"
                                        labels={b.herkunft}
                                    />
                                </div>
                            ),
                        },
                        {
                            id: 'kategorien',
                            header: 'Kategorien',
                            width: 180,
                            sortValue: (b) => b.kategorien.join(', '),
                            cell: (b) => (
                                <div className="flex flex-wrap gap-1">
                                    <ListBadges
                                        list="kategorien"
                                        labels={b.kategorien}
                                    />
                                </div>
                            ),
                        },
                        {
                            id: 'status',
                            header: 'Status',
                            width: 110,
                            sortValue: (b) => b.status,
                            cell: (b) => <StatusBadge status={b.status} />,
                        },
                        {
                            id: 'veroeffentlicht',
                            header: 'Veröffentlicht',
                            width: 150,
                            sortValue: (b) =>
                                b.veroeffentlicht === null
                                    ? -1
                                    : b.entwurf
                                      ? b.veroeffentlicht + 0.5
                                      : b.veroeffentlicht,
                            cell: (b) => (
                                <RevisionBadges
                                    published={b.veroeffentlicht}
                                    revision={b.revision}
                                    draft={b.entwurf}
                                />
                            ),
                        },
                        {
                            id: 'geaendert',
                            header: 'Geändert',
                            width: 120,
                            sortValue: (b) => b.geaendert ?? '',
                            cell: (b) =>
                                b.geaendert
                                    ? new Date(b.geaendert).toLocaleDateString(
                                          'de-CH',
                                      )
                                    : '–',
                        },
                        {
                            id: 'aktionen',
                            label: 'Aktionen',
                            header: <span className="sr-only">Aktionen</span>,
                            width: 56,
                            fixed: true,
                            sticky: 'right',
                            className: 'py-1',
                            cell: (b) => (
                                <IconAction
                                    icon={Pencil}
                                    label={`${b.abkuerzung || b.bezeichnung}: Details und Bearbeiten`}
                                    href={`/admin/begriffe/${b.id}`}
                                />
                            ),
                        },
                    ]}
                />
            </div>
        </>
    );
}

type Ansicht = {
    layout: ViewLayout;
    gruppierung: Gruppierung;
    sort: GridSort;
};

const VORGABE: Ansicht = { layout: 'table', gruppierung: '', sort: null };
const ANSICHT_KEY = 'glossary.admin.terms.view';

/** Sortierbare Spalten für das Ansichtsmenü (gleiche IDs wie in der Tabelle). */
const SORTIERUNG = [
    { id: 'abkuerzung', label: 'Abkürzung' },
    { id: 'bezeichnung', label: 'Bezeichnung' },
    { id: 'kurz', label: 'Kurzbeschreibung' },
    { id: 'herkunft', label: 'Herkunft' },
    { id: 'kategorien', label: 'Kategorien' },
    { id: 'status', label: 'Status' },
    { id: 'veroeffentlicht', label: 'Veröffentlicht' },
    { id: 'geaendert', label: 'Geändert' },
];

function ladeAnsicht(): Ansicht {
    try {
        const saved = JSON.parse(
            localStorage.getItem(ANSICHT_KEY) ?? '{}',
        ) as Partial<Ansicht>;

        return {
            layout:
                saved.layout === 'cards' || saved.layout === 'bars'
                    ? saved.layout
                    : 'table',
            gruppierung:
                saved.gruppierung && saved.gruppierung in GRUPPEN
                    ? saved.gruppierung
                    : '',
            sort:
                saved.sort && SORTIERUNG.some((x) => x.id === saved.sort?.id)
                    ? saved.sort
                    : null,
        };
    } catch {
        return VORGABE;
    }
}

function speichereAnsicht(ansicht: Ansicht) {
    try {
        localStorage.setItem(ANSICHT_KEY, JSON.stringify(ansicht));
    } catch {
        // ohne Speicher gilt die Ansicht bis zum Neuladen
    }
}

/** Labels einmal, alphabetisch. */
function labelsAus(labels: string[]): string[] {
    return [...new Set(labels)].sort((a, b) => a.localeCompare(b, 'de-CH'));
}

/** Kachel der Begriffstabelle (#143). */
/** Balken (#205): kompakt, eine Zeile je Begriff – die frühere Kachel (#143). */
function Balken({ begriff: b }: { begriff: Begriff }) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="min-w-0 flex-1">
                <Link
                    href={`/admin/begriffe/${b.id}`}
                    className="font-semibold text-link underline-offset-4 hover:underline"
                    translate="no"
                >
                    {[b.abkuerzung, b.bezeichnung]
                        .filter(Boolean)
                        .join(' – ') || '(ohne Bezeichnung)'}
                </Link>
                {b.kurz ? (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {b.kurz}
                    </p>
                ) : null}
                <div className="mt-2 flex flex-wrap gap-1">
                    <ListBadges list="herkunft" labels={b.herkunft} />
                    <ListBadges list="kategorien" labels={b.kategorien} />
                </div>
            </div>
            <div className="flex flex-wrap items-center gap-1">
                <StatusBadge status={b.status} />
                <RevisionBadges
                    published={b.veroeffentlicht}
                    revision={b.revision}
                    draft={b.entwurf}
                />
                {!b.aktiv ? (
                    <StateBadge state="inactive">inaktiv</StateBadge>
                ) : null}
            </div>
        </div>
    );
}

/** Gruppen für die Tabelle; ein Begriff mit mehreren Kategorien erscheint in jeder. */
function gruppieren(
    liste: Begriff[],
    gruppierung: Gruppierung,
): { titel: string; begriffe: Begriff[] }[] {
    if (gruppierung === '') {
        return [{ titel: '', begriffe: liste }];
    }
    if (gruppierung === 'angelegt' || gruppierung === 'geaendert') {
        const nachZeit = new Map<
            string,
            { titel: string; begriffe: Begriff[] }
        >();
        for (const b of liste) {
            const z = zeitraum(b[gruppierung]);
            const g = nachZeit.get(z.key) ?? { titel: z.titel, begriffe: [] };
            g.begriffe.push(b);
            nachZeit.set(z.key, g);
        }

        return [...nachZeit.entries()]
            .sort(([a], [b]) => b.localeCompare(a))
            .map(([, g]) => g);
    }
    const map = new Map<string, Begriff[]>();
    const add = (key: string, b: Begriff) =>
        map.set(key, [...(map.get(key) ?? []), b]);
    for (const b of liste) {
        if (gruppierung === 'kategorie') {
            if (b.kategorien.length === 0) {
                add('ohne Kategorie', b);
            }
            b.kategorien.forEach((k) => add(k, b));
        } else if (gruppierung === 'status') {
            add(b.status, b);
        } else {
            add(
                b.veroeffentlicht === null
                    ? 'nicht veröffentlicht'
                    : b.entwurf
                      ? 'veröffentlicht, mit Entwurf'
                      : 'veröffentlicht',
                b,
            );
        }
    }

    return [...map.entries()]
        .sort(([a], [b]) => a.localeCompare(b, 'de'))
        .map(([titel, begriffe]) => ({ titel, begriffe }));
}

/**
 * Bearbeiten in der Zelle (#67): Klick oder Enter öffnet das Feld, Enter bzw. Verlassen speichert, Escape bricht ab.
 * Gesperrt (offizielles Feld ohne Recht): nur Anzeige.
 */
function Zelle({
    begriff,
    feld,
    wert,
    gesperrt = false,
    max,
}: {
    begriff: Begriff;
    feld: Feld;
    wert: string;
    gesperrt?: boolean;
    max?: number;
}) {
    const [offen, setOffen] = useState(false);
    const [entwurf, setEntwurf] = useState(wert);
    const [fehler, setFehler] = useState<string | null>(null);
    const titel = begriff.abkuerzung || begriff.bezeichnung;

    if (gesperrt) {
        return (
            <span title="Offizielles Feld – ändern nur mit Recht «Offizielle Felder»">
                {wert || '–'}
            </span>
        );
    }

    const speichern = () => {
        setOffen(false);
        if (entwurf.trim() === wert.trim()) {
            return;
        }
        router.patch(
            `/admin/begriffe/${begriff.id}/feld`,
            { feld, wert: entwurf },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    setFehler(errors.wert ?? 'Nicht gespeichert.');
                    setEntwurf(wert);
                },
                onSuccess: () => setFehler(null),
            },
        );
    };
    const taste = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            speichern();
        } else if (e.key === 'Escape') {
            setEntwurf(wert);
            setOffen(false);
        }
    };

    return (
        <>
            {offen ? (
                <Input
                    aria-label={`${titel}: ${feld === 'short_description' ? 'Kurzbeschreibung' : feld === 'official_name' ? 'Bezeichnung' : 'Abkürzung'}`}
                    value={entwurf}
                    maxLength={max}
                    onChange={(e) => setEntwurf(e.target.value)}
                    onBlur={speichern}
                    onKeyDown={taste}
                    ref={(el) => el?.focus()}
                    className="h-8"
                />
            ) : (
                <button
                    type="button"
                    onClick={() => {
                        setEntwurf(wert);
                        setOffen(true);
                    }}
                    className="w-full rounded-sm text-left hover:bg-accent/60 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    title="Klicken zum Ändern"
                >
                    {wert || <span className="text-muted-foreground">–</span>}
                </button>
            )}
            {fehler ? (
                <span className="mt-1 block text-xs text-destructive-foreground">
                    {fehler}
                </span>
            ) : null}
        </>
    );
}
