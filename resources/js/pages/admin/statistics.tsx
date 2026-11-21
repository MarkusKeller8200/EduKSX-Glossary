import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';

type Summe = { kanal: string; art: string; anzahl: number };
type Punkt = { tag: string; kanal: string; anzahl: number };
type TopBegriff = {
    code: string;
    titel: string;
    id: string | null;
    web: number;
    extension: number;
    embed: number;
    summe: number;
};
type Eintrag = { name: string; anzahl: number };

/** Farben je Kanal (Balken und Legende); die Werte stehen zusätzlich als Zahl daneben. */
const FARBE: Record<string, string> = {
    web: 'bg-sky-600',
    extension: 'bg-amber-500',
    embed: 'bg-emerald-600',
    api: 'bg-slate-500',
    mcp: 'bg-violet-600',
};

const zahl = (n: number) => n.toLocaleString('de-CH');

/**
 * Statistik ohne Cookies (#495): Tageszähler je Kanal – keine IP-Adressen, keine Wiedererkennung. «Begriffe» zählt
 * auf der Website die Aufrufe der Begriffsseite, bei Erweiterung und Einbindung die Klicks auf «Im Glossary öffnen».
 */
export default function Statistics({
    tage,
    kanal,
    zeitraeume,
    kanaele,
    arten,
    summen,
    verlauf,
    begriffe,
    einbindungen,
    werkzeuge,
    endpunkte,
    installationen,
}: {
    tage: number;
    kanal: string;
    zeitraeume: number[];
    kanaele: Record<string, string>;
    arten: Record<string, string>;
    summen: Summe[];
    verlauf: Punkt[];
    begriffe: TopBegriff[];
    einbindungen: Eintrag[];
    werkzeuge: Eintrag[];
    endpunkte: Eintrag[];
    installationen: { tag: string; anzahl: number }[];
}) {
    const filter = (change: { tage?: number; kanal?: string }) =>
        router.get(
            '/admin/statistik',
            { tage, kanal, ...change },
            { preserveScroll: true, preserveState: true },
        );
    const tageListe = useMemo(() => {
        const map = new Map<string, Record<string, number>>();
        for (const p of verlauf) {
            const tag = map.get(p.tag) ?? {};
            tag[p.kanal] = (tag[p.kanal] ?? 0) + p.anzahl;
            map.set(p.tag, tag);
        }
        return [...map.entries()];
    }, [verlauf]);
    const maxTag = Math.max(
        1,
        ...tageListe.map(([, v]) =>
            Object.values(v).reduce((a, b) => a + b, 0),
        ),
    );
    const proKanal = (k: string) =>
        summen.filter((s) => s.kanal === k && s.anzahl > 0);
    const durchschnitt =
        installationen.length > 0
            ? Math.round(
                  installationen.reduce((a, b) => a + b.anzahl, 0) /
                      installationen.length,
              )
            : 0;

    return (
        <>
            <Head title="Statistik" />
            <h1 className="text-3xl font-semibold tracking-tight">Statistik</h1>
            <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                Zähler pro Tag, ohne Cookies, ohne IP-Adressen und ohne
                Wiedererkennung. Bei Erweiterung und Einbindung zählt nur, was
                beim Glossary ankommt: Laden des Index (einmal täglich je
                Browser), «Im Glossary öffnen» und Meldungen – nicht, welche
                Erklärungen jemand liest.
            </p>

            <div className="mt-4 flex flex-wrap items-center gap-2 text-sm">
                {zeitraeume.map((t) => (
                    <button
                        key={t}
                        type="button"
                        onClick={() => filter({ tage: t })}
                        className={`rounded-md border px-3 py-1 ${t === tage ? 'border-primary font-medium' : 'border-border'}`}
                    >
                        {t} Tage
                    </button>
                ))}
                <span className="mx-2 text-muted-foreground">·</span>
                <button
                    type="button"
                    onClick={() => filter({ kanal: '' })}
                    className={`rounded-md border px-3 py-1 ${kanal === '' ? 'border-primary font-medium' : 'border-border'}`}
                >
                    alle Kanäle
                </button>
                {Object.entries(kanaele).map(([k, label]) => (
                    <button
                        key={k}
                        type="button"
                        onClick={() => filter({ kanal: k })}
                        className={`inline-flex items-center gap-1.5 rounded-md border px-3 py-1 ${kanal === k ? 'border-primary font-medium' : 'border-border'}`}
                    >
                        <span
                            className={`size-2.5 rounded-sm ${FARBE[k]}`}
                            aria-hidden
                        />
                        {label}
                    </button>
                ))}
            </div>

            <section className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                {Object.entries(kanaele)
                    .filter(([k]) => kanal === '' || kanal === k)
                    .map(([k, label]) => (
                        <div
                            key={k}
                            className="rounded-lg border border-border p-3 text-sm"
                        >
                            <p className="flex items-center gap-1.5 font-medium">
                                <span
                                    className={`size-2.5 rounded-sm ${FARBE[k]}`}
                                    aria-hidden
                                />
                                {label}
                            </p>
                            {proKanal(k).length === 0 ? (
                                <p className="mt-2 text-muted-foreground">
                                    keine Zahlen
                                </p>
                            ) : (
                                <dl className="mt-2 space-y-1">
                                    {proKanal(k).map((s) => (
                                        <div
                                            key={s.art}
                                            className="flex justify-between gap-2"
                                        >
                                            <dt className="text-muted-foreground">
                                                {arten[s.art] ?? s.art}
                                            </dt>
                                            <dd className="font-mono tabular-nums">
                                                {zahl(s.anzahl)}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </div>
                    ))}
            </section>

            <section className="mt-8">
                <h2 className="text-lg font-semibold">
                    Begriffe pro Tag (aufgerufen bzw. geöffnet)
                </h2>
                {tageListe.length === 0 ? (
                    <p className="mt-2 text-sm text-muted-foreground">
                        Noch keine Zahlen in diesem Zeitraum.
                    </p>
                ) : (
                    <ul className="mt-3 space-y-1 text-sm">
                        {tageListe.map(([tag, werte]) => {
                            const summe = Object.values(werte).reduce(
                                (a, b) => a + b,
                                0,
                            );
                            return (
                                <li
                                    key={tag}
                                    className="flex items-center gap-3"
                                >
                                    <span className="w-24 shrink-0 font-mono text-xs text-muted-foreground">
                                        {new Date(tag).toLocaleDateString(
                                            'de-CH',
                                        )}
                                    </span>
                                    <span
                                        className="flex h-3 flex-1 overflow-hidden rounded-sm bg-muted"
                                        aria-hidden
                                    >
                                        {Object.entries(werte).map(([k, n]) => (
                                            <span
                                                key={k}
                                                className={FARBE[k]}
                                                style={{
                                                    width: `${(n / maxTag) * 100}%`,
                                                }}
                                            />
                                        ))}
                                    </span>
                                    <span className="w-48 shrink-0 text-xs">
                                        {Object.entries(werte)
                                            .map(
                                                ([k, n]) =>
                                                    `${kanaele[k] ?? k} ${zahl(n)}`,
                                            )
                                            .join(' · ')}{' '}
                                        <span className="text-muted-foreground">
                                            (Σ {zahl(summe)})
                                        </span>
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </section>

            <section className="mt-8">
                <h2 className="text-lg font-semibold">
                    Meistgenutzte Begriffe
                </h2>
                {begriffe.length === 0 ? (
                    <p className="mt-2 text-sm text-muted-foreground">
                        Noch keine Zahlen in diesem Zeitraum.
                    </p>
                ) : (
                    <table className="mt-3 w-full max-w-3xl text-sm">
                        <thead>
                            <tr className="border-b border-border text-left">
                                <th className="py-1 font-medium">Begriff</th>
                                <th className="py-1 text-right font-medium">
                                    Website
                                </th>
                                <th className="py-1 text-right font-medium">
                                    Erweiterung
                                </th>
                                <th className="py-1 text-right font-medium">
                                    Einbindung
                                </th>
                                <th className="py-1 text-right font-medium">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {begriffe.map((b) => (
                                <tr
                                    key={b.code}
                                    className="border-b border-border/60"
                                >
                                    <td className="py-1">
                                        {b.id ? (
                                            <Link
                                                href={`/admin/begriffe/${b.id}`}
                                                className="text-link underline-offset-4 hover:underline"
                                            >
                                                {b.titel}
                                            </Link>
                                        ) : (
                                            b.titel
                                        )}
                                    </td>
                                    {(
                                        ['web', 'extension', 'embed'] as const
                                    ).map((k) => (
                                        <td
                                            key={k}
                                            className="py-1 text-right font-mono tabular-nums"
                                        >
                                            {b[k] > 0 ? zahl(b[k]) : '–'}
                                        </td>
                                    ))}
                                    <td className="py-1 text-right font-mono font-medium tabular-nums">
                                        {zahl(b.summe)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </section>

            <section className="mt-8 grid gap-6 lg:grid-cols-2">
                <Liste
                    titel="Browser-Erweiterung: Index pro Tag"
                    hinweis={`≈ aktive Installationen; Durchschnitt ${zahl(durchschnitt)} pro Tag`}
                    eintraege={installationen.map((i) => ({
                        name: new Date(i.tag).toLocaleDateString('de-CH'),
                        anzahl: i.anzahl,
                    }))}
                />
                <Liste
                    titel="Einbindung: Webseiten"
                    hinweis="Index geladen, je Domain der einbindenden Webseite"
                    eintraege={einbindungen}
                />
                <Liste
                    titel="MCP: Werkzeuge"
                    hinweis="Aufrufe je Werkzeug, ohne Eingaben"
                    eintraege={werkzeuge}
                />
                <Liste
                    titel="API: Endpunkte"
                    hinweis="erfolgreiche Abrufe"
                    eintraege={endpunkte}
                />
            </section>
        </>
    );
}

function Liste({
    titel,
    hinweis,
    eintraege,
}: {
    titel: string;
    hinweis: string;
    eintraege: Eintrag[];
}) {
    return (
        <div>
            <h2 className="text-lg font-semibold">{titel}</h2>
            <p className="text-sm text-muted-foreground">{hinweis}</p>
            {eintraege.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Noch keine Zahlen.
                </p>
            ) : (
                <ul className="mt-2 space-y-1 text-sm">
                    {eintraege.map((e) => (
                        <li
                            key={e.name}
                            className="flex justify-between gap-3 border-b border-border/60 py-0.5"
                        >
                            <span className="truncate">{e.name || '–'}</span>
                            <span className="font-mono tabular-nums">
                                {zahl(e.anzahl)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
