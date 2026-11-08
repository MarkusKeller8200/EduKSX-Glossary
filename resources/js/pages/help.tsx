import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { palette } from '@/lib/colors';
import { cn } from '@/lib/utils';

type Feld = {
    gruppe: string;
    feld: string;
    beschreibung: string;
    format: string;
    beispiel: string;
    pflicht: string;
    sichtbar: string;
    excel: string | null;
    api: string | null;
    tab: string;
};

type Reiter = { key: string; label: string };

type Thema = { tab: string; feld: string; beschreibung: string };

/** Farbe der Sichtbarkeit: öffentlich grün, Suche blau, intern orange, automatisch grau. */
function sichtbarClass(s: string): string {
    if (s.startsWith('öffentlich')) {
        return palette.emerald.badge;
    }
    if (s === 'Suche') {
        return palette.sky.badge;
    }
    if (s === 'intern') {
        return palette.amber.badge;
    }

    return palette.slate.badge;
}

/** Hilfe (#135): alle Felder eines Begriffs mit Bedeutung, Format, Sichtbarkeit, Excel-Spalte und API-Name. */
export default function Help({
    reiter,
    felder: alleFelder,
    weiteres: alleThemen,
}: {
    reiter: Reiter[];
    felder: Feld[];
    weiteres: Thema[];
}) {
    // Reiter je Rolle (#166), gewählter Reiter in der Adresse (#reiter-…)
    const start = (): string => {
        const hash =
            typeof window === 'undefined'
                ? ''
                : window.location.hash.replace('#reiter-', '');

        return reiter.some((r) => r.key === hash) ? hash : reiter[0].key;
    };
    const [aktiv, setAktiv] = useState(start);
    const waehlen = (key: string) => {
        setAktiv(key);
        window.history.replaceState(null, '', `#reiter-${key}`);
    };
    const tasten = (e: KeyboardEvent<HTMLDivElement>) => {
        const i = reiter.findIndex((r) => r.key === aktiv);
        const next =
            e.key === 'ArrowRight'
                ? (i + 1) % reiter.length
                : e.key === 'ArrowLeft'
                  ? (i - 1 + reiter.length) % reiter.length
                  : -1;
        if (next >= 0) {
            e.preventDefault();
            waehlen(reiter[next].key);
            document.getElementById(`tab-${reiter[next].key}`)?.focus();
        }
    };
    const felder = alleFelder.filter((f) => f.tab === aktiv);
    const weiteres = alleThemen.filter((t) => t.tab === aktiv);
    const gruppen = [...new Set(felder.map((f) => f.gruppe))];

    return (
        <>
            <Head title="Hilfe" />
            <h1 className="text-3xl font-semibold tracking-tight">Hilfe</h1>
            <p className="mt-2 text-muted-foreground">
                Hilfe nach Rolle: Felder eines Begriffs und die wichtigsten
                Abläufe. Jeder Eintrag im Glossary besteht aus den Feldern im
                Reiter «Gast / Benutzer». «Öffentlich» heisst: sichtbar auf der
                Website, in der{' '}
                <a
                    href="/api/docs"
                    className="text-link underline underline-offset-4"
                >
                    API
                </a>{' '}
                und in den Exporten – immer im veröffentlichten Stand. Die
                Spalten des Excel-Imports und -Exports der Redaktion heissen wie
                angegeben.
            </p>
            <div
                role="tablist"
                aria-label="Hilfe nach Rolle"
                className="mt-6 flex flex-wrap gap-1 border-b border-border"
                onKeyDown={tasten}
            >
                {reiter.map((r) => (
                    <button
                        key={r.key}
                        id={`tab-${r.key}`}
                        type="button"
                        role="tab"
                        aria-selected={r.key === aktiv}
                        aria-controls={`panel-${r.key}`}
                        tabIndex={r.key === aktiv ? 0 : -1}
                        onClick={() => waehlen(r.key)}
                        className={cn(
                            '-mb-px rounded-t-md border border-transparent px-3 py-2 text-sm',
                            r.key === aktiv
                                ? 'border-border border-b-background bg-background font-semibold'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {r.label}
                    </button>
                ))}
            </div>

            <div
                role="tabpanel"
                id={`panel-${aktiv}`}
                aria-labelledby={`tab-${aktiv}`}
            >
                <nav
                    aria-label="Gruppen"
                    className="mt-4 flex flex-wrap gap-2 text-sm"
                >
                    {gruppen.map((g) => (
                        <a
                            key={g}
                            href={`#${anker(g)}`}
                            className="text-link underline underline-offset-4"
                        >
                            {g}
                        </a>
                    ))}
                    <a
                        href="#weiteres"
                        className="text-link underline underline-offset-4"
                    >
                        Themen
                    </a>
                </nav>

                {gruppen.map((g) => (
                    <section key={g} id={anker(g)} className="mt-8">
                        <h2 className="text-xl font-semibold">{g}</h2>
                        <div className="mt-3 overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted text-left">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 font-medium"
                                        >
                                            Feld
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 font-medium"
                                        >
                                            Bedeutung
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 font-medium"
                                        >
                                            Format und Beispiel
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 font-medium"
                                        >
                                            Pflicht
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 font-medium"
                                        >
                                            Sichtbar
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 font-medium"
                                        >
                                            Excel-Spalte
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 font-medium"
                                        >
                                            API
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {felder
                                        .filter((f) => f.gruppe === g)
                                        .map((f) => (
                                            <tr
                                                key={f.feld}
                                                className="border-t border-border align-top"
                                            >
                                                <th
                                                    scope="row"
                                                    className="px-3 py-2 text-left font-medium"
                                                >
                                                    {f.feld}
                                                </th>
                                                <td className="px-3 py-2">
                                                    {f.beschreibung}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {f.format}
                                                    <span className="mt-1 block text-xs text-muted-foreground">
                                                        z. B. {f.beispiel}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2">
                                                    {f.pflicht}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Badge
                                                        variant="outline"
                                                        className={cn(
                                                            'whitespace-normal',
                                                            sichtbarClass(
                                                                f.sichtbar,
                                                            ),
                                                        )}
                                                    >
                                                        {f.sichtbar}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-2">
                                                    {f.excel ?? '–'}
                                                </td>
                                                <td className="px-3 py-2 font-mono text-xs">
                                                    {f.api ?? '–'}
                                                </td>
                                            </tr>
                                        ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ))}

                <section id="weiteres" className="mt-8">
                    <h2 className="text-xl font-semibold">Themen</h2>
                    <dl className="mt-3 grid gap-3">
                        {weiteres.map((w) => (
                            <div key={w.feld}>
                                <dt className="font-medium">{w.feld}</dt>
                                <dd className="text-muted-foreground">
                                    {w.beschreibung}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    <p className="mt-6 text-sm">
                        Etwas falsch oder fehlt ein Begriff?{' '}
                        <Link
                            href="/vorschlaege/neu"
                            className="text-link underline underline-offset-4"
                        >
                            Begriff vorschlagen
                        </Link>
                    </p>
                </section>
            </div>
        </>
    );
}

/** Anker einer Gruppe (Leerzeichen → Bindestrich). */
function anker(gruppe: string): string {
    return gruppe.toLowerCase().replace(/\s+/g, '-');
}
