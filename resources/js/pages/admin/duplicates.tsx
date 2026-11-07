import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Mitglied = {
    id: string;
    abkuerzung: string;
    bezeichnung: string;
    kurz: string;
    kategorien: string[];
    status: string;
    aktiv: boolean;
    veroeffentlicht: boolean;
};

type Gruppe = {
    signatur: string;
    art: 'both' | 'name' | 'abbreviation';
    wert: string;
    begriffe: Mitglied[];
};

const ART: Record<Gruppe['art'], string> = {
    both: 'Gleiche Abkürzung und Bezeichnung',
    name: 'Gleiche Bezeichnung',
    abbreviation: 'Gleiche Abkürzung',
};

/**
 * Doppelte Abkürzungen oder Begriffe (#16, wie im Intranet). Nichts geschieht automatisch: je Gruppe
 * zusammenführen oder als mehrdeutig belassen.
 */
export default function Duplicates({
    gruppen,
    belassen,
}: {
    gruppen: Gruppe[];
    belassen: number;
}) {
    const [sending, setSending] = useState(false);
    const post = (url: string, data: Record<string, string>) => {
        setSending(true);
        router.post(url, data, {
            preserveScroll: true,
            onFinish: () => setSending(false),
        });
    };
    const zusammenfuehren = (g: Gruppe) =>
        router.get('/admin/doppelte/zusammenfuehren', {
            ids: g.begriffe.map((b) => b.id),
        });

    return (
        <>
            <Head title="Doppelte" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Doppelte Abkürzungen oder Begriffe
                    </h1>
                    <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                        {gruppen.length === 1
                            ? '1 Gruppe'
                            : `${gruppen.length} Gruppen`}{' '}
                        mit gleicher Abkürzung oder Bezeichnung. Gleiche
                        Abkürzungen mit verschiedener Bedeutung sind erlaubt –
                        dann «Mehrdeutig belassen». Zusammenführen nur bei
                        gleicher Bedeutung; alte Adressen leiten danach weiter.
                    </p>
                </div>
                {belassen > 0 ? (
                    <Button
                        variant="outline"
                        disabled={sending}
                        onClick={() => {
                            if (
                                window.confirm(
                                    `${belassen} Entscheide «mehrdeutig belassen» aufheben? Die Gruppen werden wieder vorgeschlagen.`,
                                )
                            ) {
                                post('/admin/doppelte/zuruecksetzen', {});
                            }
                        }}
                    >
                        {belassen} belassene wieder anzeigen
                    </Button>
                ) : null}
            </div>

            {gruppen.length === 0 ? (
                <p className="mt-8 text-muted-foreground">
                    Keine möglichen Doppelten gefunden.
                </p>
            ) : null}

            <div className="mt-6 grid gap-4">
                {gruppen.map((g) => (
                    <Card key={g.signatur}>
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                            <CardTitle className="text-base">
                                <span className="font-normal text-muted-foreground">
                                    {ART[g.art]}:
                                </span>{' '}
                                <span translate="no">{g.wert}</span>
                            </CardTitle>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={sending}
                                    onClick={() =>
                                        post('/admin/doppelte/belassen', {
                                            signatur: g.signatur,
                                        })
                                    }
                                >
                                    Mehrdeutig belassen
                                </Button>
                                <Button
                                    size="sm"
                                    disabled={sending}
                                    onClick={() => zusammenfuehren(g)}
                                >
                                    Zusammenführen …
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y divide-border text-sm">
                                {g.begriffe.map((b) => (
                                    <li
                                        key={b.id}
                                        className="flex flex-wrap items-baseline gap-x-3 gap-y-1 py-2"
                                    >
                                        <Link
                                            href={`/admin/begriffe/${b.id}`}
                                            className="font-medium text-link underline-offset-4 hover:underline"
                                            translate="no"
                                        >
                                            {[b.abkuerzung, b.bezeichnung]
                                                .filter(Boolean)
                                                .join(' – ')}
                                        </Link>
                                        {b.kategorien.map((k) => (
                                            <Badge key={k} variant="outline">
                                                {k}
                                            </Badge>
                                        ))}
                                        <span className="text-muted-foreground">
                                            {b.veroeffentlicht
                                                ? 'veröffentlicht'
                                                : 'Entwurf'}
                                            {b.aktiv ? '' : ', inaktiv'} ·{' '}
                                            {b.status}
                                        </span>
                                        {b.kurz ? (
                                            <span className="basis-full text-muted-foreground">
                                                {b.kurz}
                                            </span>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}
