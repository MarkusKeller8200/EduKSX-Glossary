import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';

type Begriff = {
    slug: string;
    abkuerzung: string;
    bezeichnung: string;
    abkuerzung_offiziell: string | null;
    bezeichnung_offiziell: string | null;
    aliase: string[];
    kurz: string | null;
    beschreibung: string | null;
    url: string | null;
    kategorien: string[];
    herkunft: { label: string; name: string; url: string | null }[];
    status: string;
    gueltig_ab: string | null;
    gueltig_bis: string | null;
    revision: number;
    veroeffentlicht: string | null;
    andere: { slug: string; bezeichnung: string }[];
};

/** Detailseite eines veröffentlichten Begriffs (#24, PUB-03/04/05/07). */
export default function Term({ begriff: b }: { begriff: Begriff }) {
    const titel = [b.abkuerzung, b.bezeichnung].filter(Boolean).join(' – ');
    const offiziellAnders =
        (b.abkuerzung_offiziell && b.abkuerzung_offiziell !== b.abkuerzung) ||
        (b.bezeichnung_offiziell && b.bezeichnung_offiziell !== b.bezeichnung);

    return (
        <>
            <Head title={titel} />
            <p className="text-sm">
                <Link
                    href="/"
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← Alle Begriffe
                </Link>
            </p>

            <article className="mt-4 max-w-3xl">
                <h1 className="text-3xl font-semibold tracking-tight">
                    {b.abkuerzung ? (
                        <span translate="no">{b.abkuerzung}</span>
                    ) : null}
                    {b.abkuerzung && b.bezeichnung ? (
                        <span className="text-muted-foreground"> – </span>
                    ) : null}
                    <span translate="no">{b.bezeichnung}</span>
                </h1>

                <div className="mt-3 flex flex-wrap gap-2">
                    {b.kategorien.map((k) => (
                        <Badge key={k} variant="outline">
                            {k}
                        </Badge>
                    ))}
                    {b.status !== 'aktuell' ? (
                        <Badge variant="secondary">{b.status}</Badge>
                    ) : null}
                </div>

                {b.kurz ? <p className="mt-6 text-lg">{b.kurz}</p> : null}
                {b.beschreibung ? (
                    <p className="mt-4 whitespace-pre-line">{b.beschreibung}</p>
                ) : null}

                <dl className="mt-8 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[12rem_1fr]">
                    {offiziellAnders ? (
                        <>
                            <dt className="text-muted-foreground">
                                Offizielle Form
                            </dt>
                            <dd translate="no">
                                {[b.abkuerzung_offiziell, b.bezeichnung_offiziell]
                                    .filter(Boolean)
                                    .join(' – ')}
                            </dd>
                        </>
                    ) : null}
                    {b.aliase.length > 0 ? (
                        <>
                            <dt className="text-muted-foreground">
                                Andere Schreibweisen
                            </dt>
                            <dd translate="no">{b.aliase.join(', ')}</dd>
                        </>
                    ) : null}
                    {b.herkunft.length > 0 ? (
                        <>
                            <dt className="text-muted-foreground">Herkunft</dt>
                            <dd>
                                {b.herkunft.map((h, i) => (
                                    <span key={h.label}>
                                        {i > 0 ? ', ' : ''}
                                        {h.url ? (
                                            <a
                                                href={h.url}
                                                className="text-link underline underline-offset-4"
                                                rel="noopener noreferrer"
                                            >
                                                {h.name}
                                            </a>
                                        ) : (
                                            h.name
                                        )}
                                    </span>
                                ))}
                            </dd>
                        </>
                    ) : null}
                    {b.url ? (
                        <>
                            <dt className="text-muted-foreground">Quelle</dt>
                            <dd className="break-all">
                                <a
                                    href={b.url}
                                    className="text-link underline underline-offset-4"
                                    rel="noopener noreferrer"
                                >
                                    {b.url}
                                </a>
                            </dd>
                        </>
                    ) : null}
                    {b.gueltig_ab || b.gueltig_bis ? (
                        <>
                            <dt className="text-muted-foreground">Gültig</dt>
                            <dd>
                                {b.gueltig_ab ? `ab ${b.gueltig_ab}` : ''}
                                {b.gueltig_ab && b.gueltig_bis ? ' ' : ''}
                                {b.gueltig_bis ? `bis ${b.gueltig_bis}` : ''}
                            </dd>
                        </>
                    ) : null}
                    <dt className="text-muted-foreground">Stand</dt>
                    <dd>
                        Revision {b.revision}
                        {b.veroeffentlicht
                            ? `, veröffentlicht am ${b.veroeffentlicht}`
                            : ''}
                    </dd>
                </dl>

                {b.andere.length > 0 ? (
                    <section className="mt-8">
                        <h2 className="font-semibold">
                            Weitere Bedeutungen von «{b.abkuerzung}»
                        </h2>
                        <ul className="mt-2 list-disc space-y-1 pl-6">
                            {b.andere.map((o) => (
                                <li key={o.slug}>
                                    <Link
                                        href={`/begriffe/${o.slug}`}
                                        className="text-link underline underline-offset-4"
                                    >
                                        {o.bezeichnung}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}

                <p className="mt-10 text-xs text-muted-foreground">
                    Erklärungen ohne amtliche Verbindlichkeit; massgebend sind
                    die genannten Quellen.
                </p>
            </article>
        </>
    );
}
