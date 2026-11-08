import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { can } from '@/lib/access';
import { RevisionBadges, StateBadge, StatusBadge } from '@/components/tags';
import { stateClasses } from '@/lib/colors';
import { confirmAction } from '@/components/confirm-dialog';

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
    herkunft_pruefung: Pruefung[];
};

type Pruefung = {
    source_id: string;
    label: string;
    name: string;
    retrieved_at: string;
    evidence: string;
    note: string;
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
    restored: 'wiederhergestellt',
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
    belegstatus,
}: {
    begriff: Begriff;
    verlauf: Eintrag[];
    labels: Record<string, string>;
    listen: {
        kategorien: Option[];
        herkunft: Option[];
        status: Record<string, string>;
    };
    belegstatus: Record<string, string>;
}) {
    const [sending, setSending] = useState(false);
    const { auth } = usePage().props;
    const formFehler = (
        usePage().props.errors as Record<string, string> | undefined
    )?.form;
    const darfBearbeiten = can(auth, 'edit-terms');
    // Revision wiederherstellen bzw. Entwurf verwerfen (DEC-022)
    const wiederherstellen = async (
        revision: number,
        veroeffentlichen: boolean,
    ) => {
        const text = veroeffentlichen
            ? `Revision ${revision} wiederherstellen und gleich veröffentlichen?`
            : `Revision ${revision} als neue Revision wiederherstellen (Entwurf)?`;
        if (await confirmAction(text)) {
            post(`/admin/begriffe/${b.id}/wiederherstellen`, {
                revision,
                veroeffentlichen,
            });
        }
    };
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
    const post = (
        url: string,
        data: Record<string, string | number | boolean> = {},
    ) => {
        setSending(true);
        router.post(url, data, {
            preserveScroll: true,
            onFinish: () => setSending(false),
        });
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
                            draft={b.ungespeichert}
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
                        {b.ungespeichert &&
                        b.veroeffentlicht !== null &&
                        darfBearbeiten ? (
                            <Button
                                variant="outline"
                                disabled={sending}
                                onClick={async () => {
                                    if (
                                        await confirmAction(
                                            `Entwurf verwerfen? Der veröffentlichte Stand V${b.veroeffentlicht} wird als neue Revision wiederhergestellt.`,
                                        )
                                    ) {
                                        post(
                                            `/admin/begriffe/${b.id}/wiederherstellen`,
                                            {
                                                revision:
                                                    b.veroeffentlicht ?? 0,
                                            },
                                        );
                                    }
                                }}
                            >
                                Entwurf verwerfen
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
                                        onClick={async () => {
                                            if (
                                                await confirmAction(
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

            {formFehler ? (
                <Alert className="mt-6" variant="destructive" role="alert">
                    <AlertDescription>{formFehler}</AlertDescription>
                </Alert>
            ) : null}

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

            {b.herkunft_pruefung.length > 0 && darfBearbeiten ? (
                <HerkunftPruefen
                    id={b.id}
                    zeilen={b.herkunft_pruefung}
                    belegstatus={belegstatus}
                />
            ) : null}

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
                            {darfBearbeiten &&
                            !b.zusammengefuehrt_in &&
                            e.revision !== b.revision ? (
                                <span className="ml-auto flex flex-wrap gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={sending}
                                        onClick={() =>
                                            wiederherstellen(e.revision, false)
                                        }
                                    >
                                        Wiederherstellen
                                    </Button>
                                    {darfVeroeffentlichen ? (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={sending}
                                            onClick={() =>
                                                wiederherstellen(
                                                    e.revision,
                                                    true,
                                                )
                                            }
                                        >
                                            … und veröffentlichen
                                        </Button>
                                    ) : null}
                                </span>
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

/** Quellenprüfung (#69): Prüfdatum, Belegstatus, Hinweis je Herkunft – ohne Revision, nicht öffentlich. */
function HerkunftPruefen({
    id,
    zeilen,
    belegstatus,
}: {
    id: string;
    zeilen: Pruefung[];
    belegstatus: Record<string, string>;
}) {
    const form = useForm({ herkunft: zeilen });
    const setze = (i: number, key: keyof Pruefung, value: string) =>
        form.setData(
            'herkunft',
            form.data.herkunft.map((z, n) =>
                n === i ? { ...z, [key]: value } : z,
            ),
        );
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/begriffe/${id}/herkunft`, { preserveScroll: true });
    };

    return (
        <section id="herkunft" className="mt-10">
            <h2 className="text-lg font-semibold">Herkunft prüfen</h2>
            <p className="mt-1 text-sm text-muted-foreground">
                Prüfdatum und Belegstatus je Herkunft (redaktionell, ohne neue
                Revision). «Offiziell» nur mit amtlichem Beleg; nach einem Jahr
                erscheint der Begriff unter «Prüfung».
            </p>
            <form onSubmit={submit} className="mt-3 grid gap-3">
                {form.data.herkunft.map((z, i) => (
                    <div
                        key={z.source_id}
                        className="grid gap-2 rounded-md border border-border p-3 text-sm sm:grid-cols-[12rem_10rem_12rem_1fr]"
                    >
                        <span className="self-center font-medium">
                            {z.label}
                            <span className="block text-xs font-normal text-muted-foreground">
                                {z.name}
                            </span>
                        </span>
                        <Input
                            type="date"
                            aria-label={`${z.label}: geprüft am`}
                            value={z.retrieved_at}
                            onChange={(e) =>
                                setze(i, 'retrieved_at', e.target.value)
                            }
                        />
                        <NativeSelect
                            aria-label={`${z.label}: Belegstatus`}
                            value={z.evidence}
                            onChange={(e) =>
                                setze(i, 'evidence', e.target.value)
                            }
                        >
                            <option value="">Belegstatus …</option>
                            {Object.entries(belegstatus).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </NativeSelect>
                        <Input
                            aria-label={`${z.label}: Hinweis`}
                            placeholder="Hinweis (z. B. Fundstelle)"
                            value={z.note}
                            maxLength={255}
                            onChange={(e) => setze(i, 'note', e.target.value)}
                        />
                    </div>
                ))}
                <div>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        Prüfung speichern
                    </Button>
                </div>
            </form>
        </section>
    );
}
