import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Zeile = {
    zeile: number;
    art: 'neu' | 'geaendert' | 'fehler';
    id: string;
    titel: string;
    meldungen: string[];
    aenderungen: Record<string, string>;
};

const ART = { neu: 'neu', geaendert: 'geändert', fehler: 'Fehler' } as const;

/** Vorschau des Excel-Imports (übernommen aus dem Intranet): neu, geändert, Fehler – erst «Übernehmen» speichert. */
export default function ImportPreview({
    token,
    datei,
    plan,
    neu,
    geaendert,
    fehler,
    listen,
    fehlendeSpalten,
}: {
    token: string;
    datei: string;
    plan: Zeile[];
    neu: number;
    geaendert: number;
    fehler: number;
    listen: { Kategorien: string[]; Herkunft: string[] };
    fehlendeSpalten: string[];
}) {
    const [sending, setSending] = useState(false);
    const neueListen = [...listen.Kategorien, ...listen.Herkunft];

    return (
        <>
            <Head title="Excel-Import prüfen" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Excel-Import prüfen
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {datei}: {neu} neu, {geaendert} geändert, {fehler}{' '}
                        Fehler. Unveränderte Zeilen werden nicht angezeigt. Es
                        wird nie etwas gelöscht.
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button
                        disabled={neu + geaendert === 0 || sending}
                        onClick={() => {
                            setSending(true);
                            router.post(
                                `/admin/import/${token}`,
                                {},
                                { onFinish: () => setSending(false) },
                            );
                        }}
                    >
                        Übernehmen
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/admin/import">Abbrechen</Link>
                    </Button>
                </div>
            </div>

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

            <div className="mt-6 overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted text-left">
                        <tr>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Zeile
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Art
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Eintrag
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Änderungen bzw. Fehler
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {plan.map((p) => (
                            <tr
                                key={p.zeile}
                                className="border-t border-border align-top"
                            >
                                <td className="px-3 py-2 tabular-nums">
                                    {p.zeile}
                                </td>
                                <td className="px-3 py-2">
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
                                </td>
                                <td className="px-3 py-2" translate="no">
                                    {p.titel}
                                </td>
                                <td className="px-3 py-2">
                                    {p.art === 'fehler' ? (
                                        <ul className="list-disc pl-4 text-destructive-foreground">
                                            {p.meldungen.map((m) => (
                                                <li key={m}>{m}</li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <dl className="grid gap-0.5 text-xs">
                                            {Object.entries(p.aenderungen).map(
                                                ([f, v]) => (
                                                    <div
                                                        key={f}
                                                        className="flex gap-2"
                                                    >
                                                        <dt className="text-muted-foreground">
                                                            {f}:
                                                        </dt>
                                                        <dd translate="no">
                                                            {v}
                                                        </dd>
                                                    </div>
                                                ),
                                            )}
                                        </dl>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {plan.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-3 py-6 text-center text-muted-foreground"
                                >
                                    Keine Änderungen – alles ist schon auf
                                    diesem Stand.
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>
        </>
    );
}
