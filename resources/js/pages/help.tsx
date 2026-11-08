import { Head, Link } from '@inertiajs/react';
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
};

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
    felder,
    weiteres,
}: {
    felder: Feld[];
    weiteres: { feld: string; beschreibung: string }[];
}) {
    const gruppen = [...new Set(felder.map((f) => f.gruppe))];

    return (
        <>
            <Head title="Hilfe – Felder eines Begriffs" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Hilfe: Felder eines Begriffs
            </h1>
            <p className="mt-2 text-muted-foreground">
                Jeder Eintrag im Glossary besteht aus diesen Feldern.
                «Öffentlich» heisst: sichtbar auf der Website, in der{' '}
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
            <nav
                aria-label="Gruppen"
                className="mt-4 flex flex-wrap gap-2 text-sm"
            >
                {gruppen.map((g) => (
                    <a
                        key={g}
                        href={`#${g.toLowerCase()}`}
                        className="text-link underline underline-offset-4"
                    >
                        {g}
                    </a>
                ))}
                <a
                    href="#weiteres"
                    className="text-link underline underline-offset-4"
                >
                    Weiteres
                </a>
            </nav>

            {gruppen.map((g) => (
                <section key={g} id={g.toLowerCase()} className="mt-8">
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
                <h2 className="text-xl font-semibold">Weiteres</h2>
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
        </>
    );
}
