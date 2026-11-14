import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { proposalStatusClass } from '@/lib/proposals';

export type VorschlagKurz = {
    id: string;
    nummer: number;
    art: string;
    begriff: { slug: string; titel: string } | null;
    titel: string | null;
    status: string;
    status_label: string;
    eingereicht: string | null;
    offen: boolean;
};

/** «Meine Vorschläge» (#113): eigene Vorschläge mit Stand. */
export default function ProposalIndex({
    vorschlaege,
}: {
    vorschlaege: VorschlagKurz[];
}) {
    return (
        <>
            <Head title="Meine Vorschläge" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <h1 className="text-3xl font-semibold tracking-tight">
                    Meine Vorschläge
                </h1>
                <Button asChild>
                    <Link href="/vorschlaege/neu">Begriff vorschlagen</Link>
                </Button>
            </div>
            <p className="mt-2 max-w-prose text-sm text-muted-foreground">
                Nur für Sie sichtbar. Korrekturen zu einem Begriff starten Sie
                auf der Seite des Begriffs mit «Korrektur vorschlagen».
            </p>
            {vorschlaege.length === 0 ? (
                <p className="mt-6 text-sm text-muted-foreground">
                    Noch keine Vorschläge.
                </p>
            ) : (
                <ul className="mt-6 grid max-w-3xl gap-2">
                    {vorschlaege.map((v) => (
                        <li key={v.id}>
                            <Link
                                href={`/vorschlaege/${v.id}`}
                                className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border p-3 text-sm hover:bg-muted/50"
                            >
                                <span>
                                    <span className="text-muted-foreground">
                                        Nr. {v.nummer} · {v.art}:
                                    </span>{' '}
                                    <span className="font-medium">
                                        {v.begriff?.titel ?? v.titel ?? '–'}
                                    </span>
                                </span>
                                <span className="flex items-center gap-2">
                                    {v.eingereicht ? (
                                        <span className="text-muted-foreground">
                                            {new Date(
                                                v.eingereicht,
                                            ).toLocaleDateString('de-CH')}
                                        </span>
                                    ) : null}
                                    <Badge
                                        variant="outline"
                                        className={proposalStatusClass(
                                            v.status,
                                        )}
                                    >
                                        {v.status_label}
                                    </Badge>
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}
