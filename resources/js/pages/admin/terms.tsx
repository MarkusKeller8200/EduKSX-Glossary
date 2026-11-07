import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState, type KeyboardEvent } from 'react';
import {
    ListBadge,
    RevisionBadges,
    StateBadge,
    StatusBadge,
} from '@/components/tags';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { can } from '@/lib/access';

type Begriff = {
    id: string;
    abkuerzung: string;
    bezeichnung: string;
    kurz: string;
    kategorien: string[];
    status: string;
    aktiv: boolean;
    revision: number;
    veroeffentlicht: number | null;
};

type Feld = 'official_abbreviation' | 'official_name' | 'short_description';
type Gruppierung = '' | 'kategorie' | 'status' | 'veroeffentlichung';

const GRUPPEN: Record<Gruppierung, string> = {
    '': 'keine',
    kategorie: 'Kategorie',
    status: 'Status',
    veroeffentlichung: 'Veröffentlichung',
};

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
    const darfExcel = can(auth, 'import');
    const [suche, setSuche] = useState('');
    const [gruppierung, setGruppierung] = useState<Gruppierung>('');
    const q = suche.trim().toLowerCase();
    const liste = q
        ? begriffe.filter((b) =>
              `${b.abkuerzung} ${b.bezeichnung} ${b.kurz} ${b.kategorien.join(' ')}`
                  .toLowerCase()
                  .includes(q),
          )
        : begriffe;
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
        setAuswahl(
            new Set(
                liste
                    .filter((b) => b.veroeffentlicht !== b.revision)
                    .map((b) => b.id),
            ),
        );
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
                    {darfExcel ? (
                        <>
                            <Button asChild variant="outline">
                                <a href="/admin/export">Excel-Export</a>
                            </Button>
                            <Button asChild variant="outline">
                                <a href="/admin/export?format=csv">
                                    CSV-Export
                                </a>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href="/admin/import">Excel-Import</Link>
                            </Button>
                        </>
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
                <label className="flex items-center gap-2 text-sm">
                    <span className="text-muted-foreground">Gruppieren</span>
                    <NativeSelect
                        value={gruppierung}
                        onChange={(e) =>
                            setGruppierung(e.target.value as Gruppierung)
                        }
                    >
                        {(Object.keys(GRUPPEN) as Gruppierung[]).map((g) => (
                            <option key={g} value={g}>
                                {GRUPPEN[g]}
                            </option>
                        ))}
                    </NativeSelect>
                </label>
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

            <div className="mt-4 overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted text-left">
                        <tr>
                            <th scope="col" className="w-10 px-3 py-2">
                                <Checkbox
                                    aria-label="Alle gefilterten auswählen"
                                    checked={alleGewaehlt}
                                    onCheckedChange={(v) =>
                                        alleUmschalten(v === true)
                                    }
                                />
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Abkürzung
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Bezeichnung
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Kurzbeschreibung
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Kategorien
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Status
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Veröffentlicht
                            </th>
                        </tr>
                    </thead>
                    {gruppen.map((g) => (
                        <tbody key={g.titel || 'alle'}>
                            {g.titel ? (
                                <tr className="border-t border-border bg-muted/50">
                                    <th
                                        scope="colgroup"
                                        colSpan={7}
                                        className="px-3 py-2 text-left font-semibold"
                                    >
                                        {g.titel}{' '}
                                        <span className="font-normal text-muted-foreground">
                                            ({g.begriffe.length})
                                        </span>
                                    </th>
                                </tr>
                            ) : null}
                            {g.begriffe.map((b) => (
                                <tr
                                    key={`${g.titel}-${b.id}`}
                                    className="border-t border-border align-top"
                                >
                                    <td className="px-3 py-2">
                                        <Checkbox
                                            aria-label={`${b.abkuerzung || b.bezeichnung} auswählen`}
                                            checked={auswahl.has(b.id)}
                                            onCheckedChange={(v) =>
                                                umschalten(b.id, v === true)
                                            }
                                        />
                                    </td>
                                    <td
                                        className="px-3 py-2 font-medium"
                                        translate="no"
                                    >
                                        <Zelle
                                            begriff={b}
                                            feld="official_abbreviation"
                                            wert={b.abkuerzung}
                                            gesperrt={
                                                !offiziellErlaubt &&
                                                b.abkuerzung !== ''
                                            }
                                        />
                                        <Link
                                            href={`/admin/begriffe/${b.id}`}
                                            className="mt-1 block text-xs text-link underline-offset-4 hover:underline"
                                        >
                                            Details
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2" translate="no">
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
                                    </td>
                                    <td className="min-w-64 px-3 py-2">
                                        <Zelle
                                            begriff={b}
                                            feld="short_description"
                                            wert={b.kurz}
                                            max={160}
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex flex-wrap gap-1">
                                            {b.kategorien.map((k) => (
                                                <ListBadge
                                                    key={k}
                                                    list="kategorien"
                                                    label={k}
                                                />
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        <StatusBadge status={b.status} />
                                    </td>
                                    <td className="px-3 py-2">
                                        <RevisionBadges
                                            published={b.veroeffentlicht}
                                            revision={b.revision}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    ))}
                    {liste.length === 0 ? (
                        <tbody>
                            <tr>
                                <td
                                    colSpan={7}
                                    className="px-3 py-6 text-center text-muted-foreground"
                                >
                                    {begriffe.length === 0
                                        ? 'Noch keine Begriffe – mit dem Excel-Import übernehmen.'
                                        : 'Keine Treffer.'}
                                </td>
                            </tr>
                        </tbody>
                    ) : null}
                </table>
            </div>
        </>
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
                    : b.veroeffentlicht === b.revision
                      ? 'veröffentlicht'
                      : 'veröffentlicht, mit Entwurf',
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
