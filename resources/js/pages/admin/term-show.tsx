import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Begriff = Record<string, unknown> & {
    id: string;
    slug: string;
    revision: number;
    veroeffentlicht: number | null;
    ungespeichert: boolean;
    categories: string[];
    sources: string[];
    is_active: boolean;
    lifecycle_status: string;
};

type Eintrag = {
    revision: number;
    art: string;
    von: string | null;
    am: string;
    veroeffentlicht_am: string | null;
    felder: string[];
};

type Option = { key: string; label: string };

const ART: Record<string, string> = {
    created: 'angelegt',
    updated: 'geändert',
};

const REIHENFOLGE = [
    'official_abbreviation',
    'official_aliases',
    'own_abbreviation',
    'own_alias',
    'official_name',
    'own_name',
    'short_description',
    'description',
    'url',
    'note',
    'categories',
    'sources',
    'used_in',
    'lifecycle_status',
    'search_terms',
    'valid_from',
    'valid_until',
    'is_active',
];

function datum(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleString('de-CH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '';
}

/** Detail eines Begriffs in der Redaktion (#14): Arbeitsstand, Veröffentlichung, Verlauf. */
export default function TermShow({
    begriff: b,
    verlauf,
    labels,
    listen,
}: {
    begriff: Begriff;
    verlauf: Eintrag[];
    labels: Record<string, string>;
    listen: {
        kategorien: Option[];
        herkunft: Option[];
        status: Record<string, string>;
    };
}) {
    const [sending, setSending] = useState(false);
    const titel = [
        b.own_abbreviation || b.official_abbreviation,
        b.own_name || b.official_name,
    ]
        .filter(Boolean)
        .join(' – ');
    const wert = (key: string): string => {
        const v = b[key];
        if (key === 'categories' || key === 'sources') {
            const opts =
                key === 'categories' ? listen.kategorien : listen.herkunft;
            return (v as string[])
                .map((k) => opts.find((o) => o.key === k)?.label ?? k)
                .join(', ');
        }
        if (key === 'lifecycle_status') {
            return listen.status[String(v)] ?? String(v);
        }
        if (key === 'is_active') {
            return v ? 'ja' : 'nein';
        }
        return String(v ?? '');
    };
    const post = (url: string) => {
        setSending(true);
        router.post(
            url,
            {},
            { preserveScroll: true, onFinish: () => setSending(false) },
        );
    };

    return (
        <>
            <Head title={titel} />
            <p className="text-sm">
                <Link
                    href="/admin/begriffe"
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← Begriffe
                </Link>
            </p>
            <div className="mt-2 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1
                        className="text-3xl font-semibold tracking-tight"
                        translate="no"
                    >
                        {titel}
                    </h1>
                    <p className="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        {b.veroeffentlicht === null ? (
                            <Badge variant="secondary">
                                Entwurf – nicht öffentlich
                            </Badge>
                        ) : (
                            <Badge>
                                Veröffentlicht: Revision {b.veroeffentlicht}
                            </Badge>
                        )}
                        {b.veroeffentlicht !== null && b.ungespeichert ? (
                            <Badge variant="secondary">
                                Entwurf Revision {b.revision} noch nicht
                                veröffentlicht
                            </Badge>
                        ) : null}
                        {!b.is_active ? (
                            <Badge variant="outline">inaktiv</Badge>
                        ) : null}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <Link href={`/admin/begriffe/${b.id}/bearbeiten`}>
                            Bearbeiten
                        </Link>
                    </Button>
                    {b.ungespeichert ? (
                        <Button
                            disabled={sending}
                            onClick={() =>
                                post(`/admin/begriffe/${b.id}/veroeffentlichen`)
                            }
                        >
                            Revision {b.revision} veröffentlichen
                        </Button>
                    ) : null}
                    {b.veroeffentlicht !== null ? (
                        <>
                            <Button asChild variant="outline">
                                <a href={`/begriffe/${b.slug}`}>
                                    Auf der Website
                                </a>
                            </Button>
                            <Button
                                variant="outline"
                                disabled={sending}
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Veröffentlichung zurückziehen? Der Begriff ist danach nicht mehr öffentlich.',
                                        )
                                    ) {
                                        post(
                                            `/admin/begriffe/${b.id}/zurueckziehen`,
                                        );
                                    }
                                }}
                            >
                                Zurückziehen
                            </Button>
                        </>
                    ) : null}
                </div>
            </div>

            <h2 className="mt-8 text-lg font-semibold">
                Arbeitsstand (Revision {b.revision})
            </h2>
            <dl className="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[14rem_1fr]">
                {REIHENFOLGE.map((key) => {
                    const v = wert(key);
                    return v === '' ? null : (
                        <div key={key} className="contents">
                            <dt className="text-muted-foreground">
                                {labels[key] ?? key}
                            </dt>
                            <dd className="whitespace-pre-line" translate="no">
                                {v}
                            </dd>
                        </div>
                    );
                })}
            </dl>

            <h2 className="mt-10 text-lg font-semibold">Verlauf</h2>
            <ol className="mt-3 space-y-3 text-sm">
                {verlauf.map((e) => (
                    <li
                        key={e.revision}
                        className="rounded-lg border border-border p-3"
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-medium">
                                Revision {e.revision}
                            </span>
                            <span>{ART[e.art] ?? e.art}</span>
                            <span className="text-muted-foreground">
                                {datum(e.am)}
                                {e.von ? ` · ${e.von}` : ''}
                            </span>
                            {e.veroeffentlicht_am ? (
                                <Badge>
                                    veröffentlicht {datum(e.veroeffentlicht_am)}
                                </Badge>
                            ) : null}
                        </div>
                        {e.felder.length > 0 ? (
                            <p className="mt-1 text-muted-foreground">
                                Geändert: {e.felder.join(', ')}
                            </p>
                        ) : null}
                    </li>
                ))}
            </ol>
        </>
    );
}
