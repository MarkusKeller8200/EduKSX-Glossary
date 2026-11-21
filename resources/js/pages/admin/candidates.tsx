import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { confirmAction } from '@/components/confirm-dialog';

type Kandidat = {
    id: string;
    wort: string;
    art: 'missing' | 'wrong';
    kanal: string;
    anzahl: number;
    begriff: { id: string; titel: string } | null;
    adresse: string | null;
    kommentare: string[];
    angemeldet: boolean;
    zuletzt: string | null;
    grund: string | null;
};

const STATUS: Record<string, string> = {
    new: 'Neu',
    accepted: 'Erledigt',
    rejected: 'Verworfen',
};

/**
 * Kandidaten aus Browser-Erweiterung und Einbindung (#480): fehlende Begriffe als Entwurf anlegen, falsche
 * Erklärungen im Begriff korrigieren, Unpassendes verwerfen (kommt nicht wieder).
 */
export default function Candidates({
    status,
    kandidaten,
    anzahl,
}: {
    status: string;
    kandidaten: Kandidat[];
    anzahl: number;
}) {
    const entscheiden = async (k: Kandidat, entscheid: string) => {
        if (
            entscheid === 'rejected' &&
            !(await confirmAction(
                `«${k.wort}» verwerfen? Weitere Meldungen dieses Worts zählen nur noch hoch.`,
            ))
        ) {
            return;
        }
        router.post(
            `/admin/kandidaten/${k.id}/entscheid`,
            { entscheid },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Kandidaten" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Kandidaten
            </h1>
            <p className="mt-2 text-sm text-muted-foreground">
                Gemeldet aus der Browser-Erweiterung und der Einbindung: nur das
                Wort, die Adresse der Seite ohne Abfrage und ein freiwilliger
                Kommentar. Nichts davon ist öffentlich.
            </p>
            <nav className="mt-4 flex flex-wrap gap-2 text-sm">
                {Object.entries(STATUS).map(([key, label]) => (
                    <Link
                        key={key}
                        href={`/admin/kandidaten?status=${key}`}
                        className={`rounded-md border px-3 py-1 ${status === key ? 'border-primary font-medium' : 'border-border'}`}
                    >
                        {label}
                        {key === 'new' ? ` (${anzahl})` : ''}
                    </Link>
                ))}
            </nav>

            {kandidaten.length === 0 ? (
                <p className="mt-6 text-sm text-muted-foreground">
                    Keine Kandidaten.
                </p>
            ) : (
                <ul className="mt-6 space-y-3">
                    {kandidaten.map((k) => (
                        <li
                            key={k.id}
                            className="rounded-lg border border-border p-3 text-sm"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <span
                                    className="text-base font-medium"
                                    translate="no"
                                >
                                    {k.wort}
                                </span>
                                <Badge variant="outline">
                                    {k.art === 'missing'
                                        ? 'fehlt'
                                        : 'Erklärung falsch'}
                                </Badge>
                                <Badge variant="secondary">{k.anzahl}×</Badge>
                                <span className="text-muted-foreground">
                                    {k.kanal === 'embed'
                                        ? 'Einbindung'
                                        : 'Erweiterung'}
                                    {k.angemeldet ? ' · angemeldet' : ''}
                                    {k.zuletzt
                                        ? ` · ${new Date(k.zuletzt).toLocaleString('de-CH', { dateStyle: 'medium', timeStyle: 'short' })}`
                                        : ''}
                                </span>
                                {status === 'new' ? (
                                    <span className="ml-auto flex flex-wrap gap-2">
                                        {k.art === 'missing' ? (
                                            <Button size="sm" asChild>
                                                <Link
                                                    href={`/admin/begriffe/neu?official_abbreviation=${encodeURIComponent(k.wort)}&kandidat=${k.id}${k.adresse ? `&note=${encodeURIComponent(`Gemeldet auf ${k.adresse}`)}` : ''}`}
                                                >
                                                    Als Entwurf anlegen
                                                </Link>
                                            </Button>
                                        ) : k.begriff ? (
                                            <Button size="sm" asChild>
                                                <Link
                                                    href={`/admin/begriffe/${k.begriff.id}/bearbeiten`}
                                                >
                                                    Begriff bearbeiten
                                                </Link>
                                            </Button>
                                        ) : null}
                                        {k.art === 'wrong' ? (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    entscheiden(k, 'accepted')
                                                }
                                            >
                                                Erledigt
                                            </Button>
                                        ) : null}
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                entscheiden(k, 'rejected')
                                            }
                                        >
                                            Verwerfen
                                        </Button>
                                    </span>
                                ) : null}
                            </div>
                            {k.begriff ? (
                                <p className="mt-1">
                                    Begriff:{' '}
                                    <Link
                                        href={`/admin/begriffe/${k.begriff.id}`}
                                        className="text-link underline-offset-4 hover:underline"
                                    >
                                        {k.begriff.titel}
                                    </Link>
                                </p>
                            ) : null}
                            {k.adresse ? (
                                <p className="mt-1 break-all text-muted-foreground">
                                    {k.adresse}
                                </p>
                            ) : null}
                            {k.kommentare.length > 0 ? (
                                <ul className="mt-2 list-disc space-y-1 pl-5">
                                    {k.kommentare.map((c, i) => (
                                        <li key={i}>{c}</li>
                                    ))}
                                </ul>
                            ) : null}
                            {k.grund ? (
                                <p className="mt-1 text-muted-foreground">
                                    Grund: {k.grund}
                                </p>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}
