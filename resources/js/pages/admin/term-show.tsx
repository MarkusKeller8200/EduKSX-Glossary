import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { can } from '@/lib/access';
import { RevisionBadges, StateBadge, StatusBadge } from '@/components/tags';
import { stateClasses } from '@/lib/colors';

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
    zusammengefuehrt_in: { id: string; titel: string } | null;
    altwerte: number;
};

type Eintrag = {
    revision: number;
    art: string;
    von: string | null;
    am: string;
    veroeffentlicht_am: string | null;
    felder: string[];
    rolle: string | null;
    quelle: string | null;
    aenderungen: { feld: string; alt: string; neu: string }[];
};

const ROLLE: Record<string, string> = {
    community: 'Community',
    moderator: 'Moderation',
    admin: 'Administration',
    developer: 'Developer',
    service: 'Dienst',
    system: 'System',
};

const QUELLE: Record<string, string> = {
    excel: 'Excel-Import',
    altwerte: 'Altwerte',
    tabelle: 'Tabelle',
};

type Option = { key: string; label: string };

const ART: Record<string, string> = {
    created: 'angelegt',
    updated: 'geändert',
    merged: 'zusammengeführt',
};

const REIHENFOLGE = [
    'official_abbreviation',
    'official_aliases',
    'alternative_aliases',
    'official_name',
    'short_description',
    'description',
    'url',
    'note',
    'categories',
    'sources',
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
    const { auth } = usePage().props;
    const darfVeroeffentlichen = can(auth, 'publish');
    const titel = [b.official_abbreviation, b.official_name]
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
        return typeof v === 'string' || typeof v === 'number' ? String(v) : '';
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
                        <RevisionBadges
                            published={b.veroeffentlicht}
                            revision={b.revision}
                        />
                        <StatusBadge status={b.lifecycle_status} />
                        {!b.is_active ? (
                            <StateBadge state="inactive">inaktiv</StateBadge>
                        ) : null}
                        {b.zusammengefuehrt_in ? (
                            <StateBadge state="merged">
                                zusammengeführt
                            </StateBadge>
                        ) : null}
                        <span className="text-muted-foreground">
                            {b.veroeffentlicht === null
                                ? 'nicht öffentlich'
                                : b.ungespeichert
                                  ? `öffentlich ist V${b.veroeffentlicht}`
                                  : 'öffentlich'}
                        </span>
                    </p>
                </div>
                {b.zusammengefuehrt_in ? null : (
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
                                    post(
                                        `/admin/begriffe/${b.id}/veroeffentlichen`,
                                    )
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
                                {darfVeroeffentlichen ? (
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
                                ) : null}
                            </>
                        ) : null}
                    </div>
                )}
            </div>

            {b.zusammengefuehrt_in ? (
                <Alert className="mt-6" role="note">
                    <AlertDescription>
                        Dieser Begriff wurde zusammengeführt in{' '}
                        <Link
                            href={`/admin/begriffe/${b.zusammengefuehrt_in.id}`}
                            className="text-link underline-offset-4 hover:underline"
                        >
                            {b.zusammengefuehrt_in.titel}
                        </Link>
                        . Er ist nur noch eine Weiterleitung und kann nicht mehr
                        bearbeitet werden.
                    </AlertDescription>
                </Alert>
            ) : null}

            {b.altwerte > 0 ? (
                <Alert className="mt-6" role="note">
                    <AlertDescription>
                        {b.altwerte === 1
                            ? '1 Altwert'
                            : `${b.altwerte} Altwerte`}{' '}
                        aus dem Intranet-Format (eigene Form, «Intern genutzt
                        in») noch nicht zugeordnet –{' '}
                        <Link
                            href="/admin/altwerte"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            Altwerte
                        </Link>
                        .
                    </AlertDescription>
                </Alert>
            ) : null}

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
                            <span className="font-medium">V{e.revision}</span>
                            <span>{ART[e.art] ?? e.art}</span>
                            <span className="text-muted-foreground">
                                {datum(e.am)}
                                {e.von ? ` · ${e.von}` : ''}
                                {e.rolle
                                    ? ` (${ROLLE[e.rolle] ?? e.rolle})`
                                    : ''}
                                {e.quelle
                                    ? ` · ${QUELLE[e.quelle] ?? e.quelle}`
                                    : ''}
                            </span>
                            {e.veroeffentlicht_am ? (
                                <Badge
                                    variant="outline"
                                    className={stateClasses.published}
                                    title={`veröffentlicht ${datum(e.veroeffentlicht_am)}`}
                                >
                                    V{e.revision} ·{' '}
                                    {datum(e.veroeffentlicht_am)}
                                </Badge>
                            ) : null}
                        </div>
                        {e.aenderungen.length > 0 ? (
                            <dl className="mt-2 grid gap-x-4 gap-y-1 text-xs sm:grid-cols-[12rem_1fr]">
                                {e.aenderungen.map((a) => (
                                    <div key={a.feld} className="contents">
                                        <dt className="text-muted-foreground">
                                            {a.feld}
                                        </dt>
                                        <dd translate="no">
                                            {a.alt !== '' ? (
                                                <del className="text-muted-foreground">
                                                    {a.alt}
                                                </del>
                                            ) : null}
                                            {a.alt !== '' && a.neu !== ''
                                                ? ' → '
                                                : ''}
                                            {a.neu !== '' ? (
                                                <ins className="no-underline">
                                                    {a.neu}
                                                </ins>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    (geleert)
                                                </span>
                                            )}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        ) : e.felder.length > 0 ? (
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
