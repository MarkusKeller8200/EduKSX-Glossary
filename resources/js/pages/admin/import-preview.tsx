import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import DataGrid from '@/components/data-grid';

type Zeile = {
    zeile: number;
    art: 'neu' | 'geaendert' | 'fehler';
    id: string;
    titel: string;
    meldungen: string[];
    aenderungen: Record<string, string>;
    werte: Record<string, string>;
    vorher: Record<string, string>;
};

type Auswahl = Record<
    string,
    { aus: boolean; felder_aus: string[]; werte: Record<string, string> }
>;

const ART = { neu: 'neu', geaendert: 'geändert', fehler: 'Fehler' } as const;

function Schalter({
    id,
    label,
    hinweis,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    hinweis: string;
    checked: boolean;
    onChange: (v: boolean) => void;
}) {
    return (
        <label
            htmlFor={id}
            className="flex cursor-pointer items-start gap-2 text-sm"
            title={hinweis}
        >
            <input
                id={id}
                type="checkbox"
                role="switch"
                aria-checked={checked}
                className="mt-0.5"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
            <span>
                {label}
                <span className="block text-xs text-muted-foreground">
                    {hinweis}
                </span>
            </span>
        </label>
    );
}

/**
 * Vorschau des Excel-Imports: neu, geändert, Fehler – erst «Übernehmen» speichert. Zeilen und Felder abwählen, alle
 * Felder anzeigen und Werte überschreiben (#505).
 */
export default function ImportPreview({
    token,
    datei,
    plan,
    neu,
    geaendert,
    fehler,
    listen,
    fehlendeSpalten,
    auswahl,
}: {
    token: string;
    datei: string;
    plan: Zeile[];
    neu: number;
    geaendert: number;
    fehler: number;
    listen: { Kategorien: string[]; Herkunft: string[] };
    fehlendeSpalten: string[];
    auswahl: Auswahl | null;
}) {
    const [sending, setSending] = useState(false);
    const [alleFelder, setAlleFelder] = useState(false);
    const [detail, setDetail] = useState(
        Object.values(auswahl ?? {}).some((a) => a.felder_aus.length > 0),
    );
    const [bearbeiten, setBearbeiten] = useState(
        Object.values(auswahl ?? {}).some((a) => Object.keys(a.werte).length),
    );
    const [aus, setAus] = useState<Set<number>>(
        () =>
            new Set(
                Object.entries(auswahl ?? {})
                    .filter(([, a]) => a.aus)
                    .map(([z]) => Number(z)),
            ),
    );
    const [felderAus, setFelderAus] = useState<Record<number, string[]>>(() =>
        Object.fromEntries(
            Object.entries(auswahl ?? {}).map(([z, a]) => [
                Number(z),
                a.felder_aus,
            ]),
        ),
    );
    const [werte, setWerte] = useState<Record<number, Record<string, string>>>(
        () =>
            Object.fromEntries(
                Object.entries(auswahl ?? {}).map(([z, a]) => [
                    Number(z),
                    a.werte,
                ]),
            ),
    );
    const [ungeprueft, setUngeprueft] = useState(false);
    // nur ein Feld zugleich bearbeiten (viele Eingabefelder auf einmal blockieren den Browser)
    const [offenesFeld, setOffenesFeld] = useState<string | null>(null);
    const neueListen = [...listen.Kategorien, ...listen.Herkunft];

    const importierbar = plan.filter((p) => p.art !== 'fehler');
    const gewaehlt = importierbar.filter((p) => !aus.has(p.zeile));
    const gewaehltNeu = gewaehlt.filter((p) => p.art === 'neu').length;
    const gewaehltGeaendert = gewaehlt.length - gewaehltNeu;

    const payload = (): Auswahl => {
        const out: Auswahl = {};
        for (const p of plan) {
            const eintrag = {
                aus: aus.has(p.zeile),
                felder_aus: detail ? (felderAus[p.zeile] ?? []) : [],
                werte: bearbeiten ? (werte[p.zeile] ?? {}) : {},
            };
            if (
                eintrag.aus ||
                eintrag.felder_aus.length > 0 ||
                Object.keys(eintrag.werte).length > 0
            ) {
                out[String(p.zeile)] = eintrag;
            }
        }
        return out;
    };

    const wert = (p: Zeile, f: string): string =>
        werte[p.zeile]?.[f] ?? p.werte[f] ?? p.aenderungen[f] ?? '';

    const felder = (p: Zeile): string[] => {
        if (alleFelder || (bearbeiten && p.art === 'fehler')) {
            return Object.keys(p.werte);
        }
        return Object.keys(p.aenderungen);
    };

    const toggleFeld = (zeile: number, f: string, an: boolean) =>
        setFelderAus((alt) => {
            const liste = new Set(alt[zeile] ?? []);
            if (an) {
                liste.delete(f);
            } else {
                liste.add(f);
            }
            return { ...alt, [zeile]: [...liste] };
        });

    const setzeWert = (zeile: number, f: string, v: string) => {
        setUngeprueft(true);
        setWerte((alt) => ({ ...alt, [zeile]: { ...alt[zeile], [f]: v } }));
    };

    const columns = useMemo(
        () => [
            {
                id: 'auswahl',
                header: (
                    <span className="sr-only">Zum Importieren gewählt</span>
                ),
                label: 'Auswahl',
                width: 56,
                fixed: true,
                sticky: 'left' as const,
                cell: (p: Zeile) =>
                    p.art === 'fehler' ? null : (
                        <input
                            type="checkbox"
                            aria-label={`${p.titel} importieren`}
                            checked={!aus.has(p.zeile)}
                            onChange={(e) =>
                                setAus((alt) => {
                                    const n = new Set(alt);
                                    if (e.target.checked) {
                                        n.delete(p.zeile);
                                    } else {
                                        n.add(p.zeile);
                                    }
                                    return n;
                                })
                            }
                        />
                    ),
            },
            {
                id: 'zeile',
                header: 'Zeile',
                width: 80,
                className: 'tabular-nums',
                sortValue: (p: Zeile) => p.zeile,
                cell: (p: Zeile) => p.zeile,
            },
            {
                id: 'art',
                header: 'Art',
                width: 110,
                sortValue: (p: Zeile) => p.art,
                cell: (p: Zeile) => (
                    <Badge
                        variant={
                            p.art === 'fehler'
                                ? 'destructive'
                                : p.art === 'neu'
                                  ? 'default'
                                  : 'secondary'
                        }
                    >
                        {ART[p.art]}
                    </Badge>
                ),
            },
            {
                id: 'eintrag',
                header: 'Eintrag',
                width: 220,
                noTranslate: true,
                sortValue: (p: Zeile) => p.titel,
                cell: (p: Zeile) => p.titel,
            },
            {
                id: 'aenderungen',
                header: alleFelder
                    ? 'Felder bzw. Fehler'
                    : 'Änderungen bzw. Fehler',
                width: 620,
                grow: true,
                cell: (p: Zeile) => {
                    const liste = felder(p);
                    const zeileAus = aus.has(p.zeile);
                    return (
                        <div className={zeileAus ? 'opacity-50' : undefined}>
                            {p.meldungen.length > 0 ? (
                                <ul
                                    className={`mb-2 list-disc pl-4 text-xs ${p.art === 'fehler' ? 'text-destructive' : 'text-muted-foreground'}`}
                                >
                                    {p.meldungen.map((m) => (
                                        <li key={m}>{m}</li>
                                    ))}
                                </ul>
                            ) : null}
                            {liste.length > 0 ? (
                                <dl
                                    className={`grid items-start gap-x-3 gap-y-1 text-xs ${detail && p.art !== 'fehler' ? 'grid-cols-[auto_minmax(8rem,max-content)_1fr]' : 'grid-cols-[minmax(8rem,max-content)_1fr]'}`}
                                >
                                    {liste.map((f) => {
                                        const geaendert = f in p.aenderungen;
                                        const feldAus = (
                                            felderAus[p.zeile] ?? []
                                        ).includes(f);
                                        return (
                                            <div key={f} className="contents">
                                                {detail &&
                                                p.art !== 'fehler' ? (
                                                    <input
                                                        type="checkbox"
                                                        className="mt-0.5"
                                                        aria-label={`${f} importieren`}
                                                        disabled={!geaendert}
                                                        checked={
                                                            geaendert &&
                                                            !feldAus
                                                        }
                                                        onChange={(e) =>
                                                            toggleFeld(
                                                                p.zeile,
                                                                f,
                                                                e.target
                                                                    .checked,
                                                            )
                                                        }
                                                    />
                                                ) : null}
                                                <dt
                                                    className={`text-muted-foreground ${geaendert ? '' : 'opacity-70'}`}
                                                >
                                                    {f}
                                                </dt>
                                                <dd
                                                    translate="no"
                                                    className={`min-w-0 break-words whitespace-pre-wrap ${feldAus ? 'text-muted-foreground line-through' : geaendert ? '' : 'text-muted-foreground'}`}
                                                >
                                                    {bearbeiten &&
                                                    (geaendert ||
                                                        p.art === 'fehler' ||
                                                        alleFelder) ? (
                                                        offenesFeld ===
                                                        `${p.zeile}:${f}` ? (
                                                            <Textarea
                                                                autoFocus
                                                                aria-label={`${f} für ${p.titel}`}
                                                                className="[field-sizing:fixed] min-h-16 w-full py-1 text-xs md:text-xs"
                                                                rows={3}
                                                                value={wert(
                                                                    p,
                                                                    f,
                                                                )}
                                                                onChange={(e) =>
                                                                    setzeWert(
                                                                        p.zeile,
                                                                        f,
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                onBlur={() =>
                                                                    setOffenesFeld(
                                                                        null,
                                                                    )
                                                                }
                                                                onKeyDown={(
                                                                    e,
                                                                ) => {
                                                                    if (
                                                                        e.key ===
                                                                        'Escape'
                                                                    ) {
                                                                        setOffenesFeld(
                                                                            null,
                                                                        );
                                                                    }
                                                                }}
                                                            />
                                                        ) : (
                                                            <button
                                                                type="button"
                                                                title="Wert ändern"
                                                                aria-label={`${f} für ${p.titel} ändern`}
                                                                onClick={() =>
                                                                    setOffenesFeld(
                                                                        `${p.zeile}:${f}`,
                                                                    )
                                                                }
                                                                className={`w-full rounded-sm border border-dashed border-border px-1 text-left hover:bg-muted ${werte[p.zeile]?.[f] !== undefined ? 'bg-warning-soft' : ''}`}
                                                            >
                                                                {wert(p, f) ||
                                                                    '–'}
                                                            </button>
                                                        )
                                                    ) : (
                                                        wert(p, f) || '–'
                                                    )}
                                                    {p.art === 'geaendert' &&
                                                    geaendert &&
                                                    alleFelder ? (
                                                        <span className="block text-muted-foreground">
                                                            bisher:{' '}
                                                            {p.vorher[f] || '–'}
                                                        </span>
                                                    ) : null}
                                                </dd>
                                            </div>
                                        );
                                    })}
                                </dl>
                            ) : null}
                        </div>
                    );
                },
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [aus, felderAus, werte, alleFelder, detail, bearbeiten, offenesFeld],
    );

    return (
        <>
            <Head title="Excel-Import prüfen" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Excel-Import prüfen
                    </h1>
                </div>
                <div className="flex gap-2">
                    <Button
                        disabled={gewaehlt.length === 0 || sending}
                        onClick={() => {
                            setSending(true);
                            router.post(
                                `/admin/import/${token}`,
                                { zeilen: payload() },
                                { onFinish: () => setSending(false) },
                            );
                        }}
                    >
                        Übernehmen ({gewaehlt.length})
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/admin/import">Abbrechen</Link>
                    </Button>
                </div>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">
                {datei}: {neu} neu, {geaendert} geändert, {fehler} Fehler –
                gewählt {gewaehltNeu} neu und {gewaehltGeaendert} geändert.
                Unveränderte Zeilen werden nicht angezeigt. Es wird nie etwas
                gelöscht.
            </p>

            {fehlendeSpalten.length > 0 ? (
                <Alert className="mt-6">
                    <AlertDescription>
                        Diese Spalten fehlen in der Datei und bleiben
                        unverändert: {fehlendeSpalten.join(', ')}
                    </AlertDescription>
                </Alert>
            ) : null}
            {neueListen.length > 0 ? (
                <Alert className="mt-6">
                    <AlertDescription>
                        Wird neu angelegt –{' '}
                        {listen.Kategorien.length > 0
                            ? `Kategorien: ${listen.Kategorien.join(', ')}. `
                            : ''}
                        {listen.Herkunft.length > 0
                            ? `Herkunft: ${listen.Herkunft.join(', ')}.`
                            : ''}
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="mt-6 flex flex-wrap items-start gap-x-8 gap-y-3 rounded-lg border border-border p-4">
                <Schalter
                    id="alle-felder"
                    label="Alle importierbaren Felder anzeigen"
                    hinweis="auch leere und unveränderte Felder"
                    checked={alleFelder}
                    onChange={setAlleFelder}
                />
                <Schalter
                    id="detailauswahl"
                    label="Detailauswahl"
                    hinweis="einzelne Felder nicht importieren"
                    checked={detail}
                    onChange={setDetail}
                />
                <Schalter
                    id="werte-ueberschreiben"
                    label="Werte überschreiben"
                    hinweis="Wert anklicken, um ihn vor dem Import zu ändern"
                    checked={bearbeiten}
                    onChange={setBearbeiten}
                />
                <span className="ml-auto flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setAus(new Set())}
                    >
                        Alle wählen
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            setAus(new Set(importierbar.map((p) => p.zeile)))
                        }
                    >
                        Keine
                    </Button>
                    {bearbeiten ? (
                        <Button
                            size="sm"
                            variant={ungeprueft ? 'default' : 'outline'}
                            disabled={sending}
                            onClick={() => {
                                setSending(true);
                                router.post(
                                    `/admin/import/${token}/pruefen`,
                                    { zeilen: payload() },
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setUngeprueft(false),
                                        onFinish: () => setSending(false),
                                    },
                                );
                            }}
                        >
                            Erneut prüfen
                        </Button>
                    ) : null}
                </span>
                {bearbeiten && ungeprueft ? (
                    <p className="w-full text-xs text-muted-foreground">
                        Geänderte Werte sind noch nicht geprüft – «Erneut
                        prüfen» zeigt Fehler vor dem Übernehmen.
                    </p>
                ) : null}
            </div>

            <div className="mt-6">
                <DataGrid
                    id="admin.import-preview"
                    caption="Vorschau des Imports"
                    rows={plan}
                    getRowId={(p: Zeile) => String(p.zeile)}
                    empty="Keine Änderungen – alles ist schon auf diesem Stand."
                    columns={columns}
                />
            </div>
        </>
    );
}
