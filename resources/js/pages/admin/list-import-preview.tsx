import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { palette } from '@/lib/colors';
import { cn } from '@/lib/utils';

type Zeile = {
    zeile: number;
    art: 'neu' | 'geaendert' | 'fehler';
    titel: string;
    meldungen: string[];
    aenderungen: Record<string, string>;
};

const TITEL = { kategorien: 'Kategorien', herkunft: 'Herkunft' } as const;
const ART = {
    neu: { text: 'neu', cls: palette.emerald.badge },
    geaendert: { text: 'geändert', cls: palette.sky.badge },
    fehler: { text: 'Fehler', cls: palette.rose.badge },
} as const;

/** Vorschau des Listen-Imports (#101): erst «Übernehmen» speichert; Fehlerzeilen werden übersprungen. */
export default function ListImportPreview({
    liste,
    token,
    datei,
    zeilen,
    plan,
}: {
    liste: keyof typeof TITEL;
    token: string;
    datei: string;
    zeilen: number;
    plan: Zeile[];
}) {
    const [sending, setSending] = useState(false);
    const count = (art: Zeile['art']) =>
        plan.filter((p) => p.art === art).length;
    const ok = count('neu') + count('geaendert');

    return (
        <>
            <Head title={`Import ${TITEL[liste]}`} />
            <p className="text-sm">
                <Link
                    href="/admin/listen"
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← Listen
                </Link>
            </p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                Import {TITEL[liste]}: Vorschau
            </h1>
            <p className="mt-2 text-sm text-muted-foreground">
                {datei} · {zeilen} Zeilen · {count('neu')} neu ·{' '}
                {count('geaendert')} geändert · {count('fehler')} Fehler ·{' '}
                {zeilen - plan.length} unverändert. Es wird nichts gelöscht.
            </p>
            {plan.length === 0 ? (
                <p className="mt-6 text-sm">Keine Änderungen.</p>
            ) : (
                <ul className="mt-6 grid max-w-4xl gap-2 text-sm">
                    {plan.map((p) => (
                        <li
                            key={p.zeile}
                            className="rounded-lg border border-border p-3"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge
                                    variant="outline"
                                    className={cn(ART[p.art].cls)}
                                >
                                    {ART[p.art].text}
                                </Badge>
                                <span className="text-muted-foreground">
                                    Zeile {p.zeile}
                                </span>
                                <span className="font-medium">
                                    {p.titel || '–'}
                                </span>
                            </div>
                            {p.meldungen.length ? (
                                <ul className="mt-1 list-disc pl-5">
                                    {p.meldungen.map((m) => (
                                        <li key={m}>{m}</li>
                                    ))}
                                </ul>
                            ) : (
                                <dl className="mt-1 grid gap-x-4 sm:grid-cols-[10rem_1fr]">
                                    {Object.entries(p.aenderungen).map(
                                        ([k, v]) => (
                                            <div key={k} className="contents">
                                                <dt className="text-muted-foreground">
                                                    {k}
                                                </dt>
                                                <dd className="break-words">
                                                    {v}
                                                </dd>
                                            </div>
                                        ),
                                    )}
                                </dl>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            <div className="mt-6 flex gap-2">
                <Button
                    disabled={ok === 0 || sending}
                    onClick={() => {
                        setSending(true);
                        router.post(
                            `/admin/listen/${liste}/import/${token}`,
                            {},
                            { onFinish: () => setSending(false) },
                        );
                    }}
                >
                    Übernehmen ({ok})
                </Button>
                <Button asChild variant="outline">
                    <Link href="/admin/listen">Abbrechen</Link>
                </Button>
            </div>
        </>
    );
}
