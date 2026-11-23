import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirmAction } from '@/components/confirm-dialog';

type Gegenstelle = {
    id: string;
    name: string;
    url: string;
    rolle: string | null;
    geholt: string | null;
    hochgeladen: string | null;
    ergebnis: string | null;
};

type Seite = {
    at: string | null;
    published: boolean | null;
    revision: number | null;
} | null;

type Richtung = 'push' | 'pull' | 'skip';

type Zeile = {
    type: 'term' | 'categories' | 'sources';
    id: string;
    title: string;
    state: string;
    here: Seite;
    there: Seite;
    direction: Richtung;
    synced_before: boolean;
};

type Vergleich =
    | {
          role: string;
          push: boolean;
          pull: boolean;
          publish: boolean;
          rows: Zeile[];
      }
    | { fehler: string };

const ZUSTAND: Record<string, string> = {
    only_here: 'nur hier',
    only_there: 'nur dort',
    changed_here: 'hier geändert',
    changed_there: 'dort geändert',
    conflict: 'Konflikt',
    different: 'unterschiedlich',
};

const FILTER: { wert: string; label: string; passt: (z: Zeile) => boolean }[] =
    [
        { wert: 'alle', label: 'Alle', passt: () => true },
        {
            wert: 'neu',
            label: 'Neu',
            passt: (z) => ['only_here', 'only_there'].includes(z.state),
        },
        {
            wert: 'geaendert',
            label: 'Geändert',
            passt: (z) =>
                ['changed_here', 'changed_there', 'different'].includes(
                    z.state,
                ),
        },
        {
            wert: 'konflikt',
            label: 'Konflikte',
            passt: (z) => z.state === 'conflict',
        },
    ];

const TYP: Record<Zeile['type'], string> = {
    term: 'Begriff',
    categories: 'Kategorie',
    sources: 'Herkunft',
};

const ROLLE: Record<string, string> = {
    master: 'Master',
    test: 'Test',
    development: 'Entwicklung',
    instance: 'Instanz',
};

function datum(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleString('de-CH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '–';
}

/** Entwicklungs-Sync (#478, #503): Gegenstellen, Vergleichstabelle, Synchronisieren pro Datensatz, Ersetzen. */
export default function Sync({
    rolle,
    darfSync,
    passwortNoetig,
    gegenstellen,
    aktiv,
    vergleich,
}: {
    rolle: { wert: string; label: string; quelle: string };
    darfSync: boolean;
    passwortNoetig: boolean;
    gegenstellen: Gegenstelle[];
    aktiv: string | null;
    vergleich: Vergleich | null;
}) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const neu = useForm({ name: '', url: 'https://', token: '' });
    const anlegen = (e: FormEvent) => {
        e.preventDefault();
        neu.post('/admin/system/sync/gegenstellen', {
            onSuccess: () => neu.reset('token'),
        });
    };
    const peer = gegenstellen.find((g) => g.id === aktiv) ?? null;

    return (
        <>
            <Head title="Entwicklungs-Sync" />
            <p className="text-sm">
                <Link
                    href="/admin/system"
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← System
                </Link>
            </p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                Entwicklungs-Sync
            </h1>
            <p className="mt-2 text-sm text-muted-foreground">
                Beide Seiten vergleichen, pro Begriff bzw. Listeneintrag die
                Richtung wählen und synchronisieren: Fehlendes wird angelegt,
                Unterschiedliches aktualisiert (neue Revision), gelöscht wird
                nie. Konten und Personendaten werden nie übertragen.
            </p>
            <p className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                Rolle dieser Instanz:{' '}
                <Badge variant="outline">{rolle.label}</Badge>
                <span className="text-muted-foreground">
                    ({rolle.quelle}; ändern unter Einstellungen bzw.{' '}
                    <code>GLOSSARY_SYNC_ROLE</code>)
                </span>
            </p>
            {!darfSync ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Als «{rolle.label}» startet diese Instanz keinen Sync. Sie
                    kann Gegenstelle für dev bzw. test sein (Servicekonto mit
                    den Scopes «glossary:sync.…»).
                </p>
            ) : null}

            <section className="mt-8">
                <h2 className="text-lg font-semibold">Gegenstellen</h2>
                {gegenstellen.length === 0 ? (
                    <p className="mt-2 text-sm text-muted-foreground">
                        Noch keine Gegenstelle erfasst.
                    </p>
                ) : (
                    <ul className="mt-3 space-y-2 text-sm">
                        {gegenstellen.map((g) => (
                            <li
                                key={g.id}
                                className={`rounded-lg border p-3 ${g.id === aktiv ? 'border-primary' : 'border-border'}`}
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link
                                        href={`/admin/system/sync/gegenstellen/${g.id}`}
                                        className="font-medium text-link underline-offset-4 hover:underline"
                                    >
                                        {g.name}
                                    </Link>
                                    <span className="text-muted-foreground">
                                        {g.url}
                                    </span>
                                    {g.rolle ? (
                                        <Badge variant="outline">
                                            {g.rolle}
                                        </Badge>
                                    ) : null}
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="ml-auto"
                                        onClick={async () => {
                                            if (
                                                await confirmAction(
                                                    `Gegenstelle «${g.name}» entfernen?`,
                                                )
                                            ) {
                                                router.delete(
                                                    `/admin/system/sync/gegenstellen/${g.id}`,
                                                );
                                            }
                                        }}
                                    >
                                        Entfernen
                                    </Button>
                                </div>
                                <p className="mt-1 text-muted-foreground">
                                    Zuletzt übernommen {datum(g.geholt)} ·
                                    gesendet {datum(g.hochgeladen)}
                                    {g.ergebnis ? ` · ${g.ergebnis}` : ''}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}

                <form
                    onSubmit={anlegen}
                    className="mt-4 grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-3"
                >
                    <div>
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={neu.data.name}
                            placeholder="prod"
                            onChange={(e) =>
                                neu.setData('name', e.target.value)
                            }
                        />
                        <InputError message={neu.errors.name} />
                    </div>
                    <div>
                        <Label htmlFor="url">Adresse</Label>
                        <Input
                            id="url"
                            value={neu.data.url}
                            onChange={(e) => neu.setData('url', e.target.value)}
                        />
                        <InputError message={neu.errors.url} />
                    </div>
                    <div>
                        <Label htmlFor="token">
                            Token des Servicekontos dort
                        </Label>
                        <Input
                            id="token"
                            type="password"
                            autoComplete="off"
                            value={neu.data.token}
                            onChange={(e) =>
                                neu.setData('token', e.target.value)
                            }
                        />
                        <InputError message={neu.errors.token} />
                    </div>
                    <p className="text-sm text-muted-foreground sm:col-span-2">
                        Auf der Gegenstelle ein Servicekonto mit
                        «glossary:sync.read», «glossary:sync.write» und optional
                        «glossary:sync.publish» anlegen. Der Token wird hier
                        verschlüsselt gespeichert und nicht mehr angezeigt.
                    </p>
                    <div className="flex items-end">
                        <Button type="submit" disabled={neu.processing}>
                            Gegenstelle erfassen
                        </Button>
                    </div>
                </form>
            </section>

            {peer ? (
                <GegenstelleDetail
                    peer={peer}
                    vergleich={vergleich}
                    darfSync={darfSync}
                    passwortNoetig={passwortNoetig}
                    eigeneRolle={rolle.wert}
                    errors={errors}
                />
            ) : null}
        </>
    );
}

const schluessel = (z: Pick<Zeile, 'type' | 'id'>) => `${z.type}:${z.id}`;

function Zeitpunkt({ seite }: { seite: Seite }) {
    if (seite === null) {
        return <span className="text-muted-foreground">fehlt</span>;
    }
    return (
        <span className="whitespace-nowrap">
            {datum(seite.at)}
            {seite.published === null ? null : (
                <span className="ml-1 text-xs text-muted-foreground">
                    {seite.published ? 'veröffentlicht' : 'Entwurf'}
                </span>
            )}
        </span>
    );
}

function RichtungWahl({
    wert,
    push,
    pull,
    zeile,
    peerName,
    onChange,
}: {
    wert: Richtung;
    push: boolean;
    pull: boolean;
    zeile: Zeile;
    peerName: string;
    onChange: (r: Richtung) => void;
}) {
    const optionen: {
        r: Richtung;
        zeichen: string;
        titel: string;
        erlaubt: boolean;
    }[] = [
        {
            r: 'pull',
            zeichen: '←',
            titel: `von «${peerName}» übernehmen`,
            erlaubt: pull && zeile.there !== null,
        },
        { r: 'skip', zeichen: '–', titel: 'überspringen', erlaubt: true },
        {
            r: 'push',
            zeichen: '→',
            titel: `nach «${peerName}» senden`,
            erlaubt: push && zeile.here !== null,
        },
    ];
    return (
        <div
            role="radiogroup"
            aria-label={`Richtung für ${zeile.title}`}
            className="inline-flex overflow-hidden rounded-md border border-border"
        >
            {optionen.map((o) => (
                <button
                    key={o.r}
                    type="button"
                    role="radio"
                    aria-checked={wert === o.r}
                    aria-label={o.titel}
                    title={o.titel}
                    disabled={!o.erlaubt}
                    onClick={() => onChange(o.r)}
                    className={`min-w-9 px-2 py-1 text-sm disabled:cursor-not-allowed disabled:opacity-30 ${
                        wert === o.r
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted'
                    }`}
                >
                    {o.zeichen}
                </button>
            ))}
        </div>
    );
}

type Fassungen = {
    here: Record<string, unknown> | null;
    there: Record<string, unknown> | null;
};

function wert(v: unknown): string {
    if (v === null || v === undefined || v === '') {
        return '–';
    }
    return typeof v === 'string' ? v : JSON.stringify(v, null, 1);
}

function Unterschiede({
    peerId,
    peerName,
    zeile,
}: {
    peerId: string;
    peerName: string;
    zeile: Zeile;
}) {
    const [daten, setDaten] = useState<Fassungen | null>(null);
    const [fehler, setFehler] = useState<string | null>(null);
    useEffect(() => {
        let aktiv = true;
        fetch(
            `/admin/system/sync/gegenstellen/${peerId}/eintrag?typ=${encodeURIComponent(zeile.type)}&id=${encodeURIComponent(zeile.id)}`,
            { headers: { Accept: 'application/json' } },
        )
            .then(async (r) => {
                const json = (await r.json()) as Fassungen & {
                    fehler?: string;
                };
                if (!aktiv) {
                    return;
                }
                if (!r.ok || json.fehler) {
                    setFehler(json.fehler ?? 'Nicht abrufbar.');
                } else {
                    setDaten(json);
                }
            })
            .catch(() => aktiv && setFehler('Nicht abrufbar.'));
        return () => {
            aktiv = false;
        };
    }, [peerId, zeile.type, zeile.id]);

    if (fehler) {
        return <p className="text-sm text-destructive">{fehler}</p>;
    }
    if (daten === null) {
        return <p className="text-sm text-muted-foreground">Lädt …</p>;
    }
    const felder = [
        ...new Set([
            ...Object.keys(daten.here ?? {}),
            ...Object.keys(daten.there ?? {}),
        ]),
    ]
        .filter(
            (f) =>
                JSON.stringify(daten.here?.[f] ?? null) !==
                JSON.stringify(daten.there?.[f] ?? null),
        )
        .sort();
    if (felder.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Keine Unterschiede in den Feldern.
            </p>
        );
    }
    return (
        <table className="w-full text-xs">
            <thead className="text-left">
                <tr>
                    <th className="py-1 pr-3 font-medium">Feld</th>
                    <th className="py-1 pr-3 font-medium">hier</th>
                    <th className="py-1 font-medium">«{peerName}»</th>
                </tr>
            </thead>
            <tbody>
                {felder.map((f) => (
                    <tr key={f} className="border-t border-border align-top">
                        <td className="py-1 pr-3 font-mono">{f}</td>
                        <td className="py-1 pr-3 break-words whitespace-pre-wrap">
                            {wert(daten.here?.[f])}
                        </td>
                        <td className="py-1 break-words whitespace-pre-wrap">
                            {wert(daten.there?.[f])}
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function GegenstelleDetail({
    peer,
    vergleich,
    darfSync,
    passwortNoetig,
    eigeneRolle,
    errors,
}: {
    peer: Gegenstelle;
    vergleich: Vergleich | null;
    darfSync: boolean;
    passwortNoetig: boolean;
    eigeneRolle: string;
    errors: Record<string, string>;
}) {
    const ok =
        vergleich !== null && !('fehler' in vergleich) ? vergleich : null;
    const vorgabe = useMemo(
        () =>
            Object.fromEntries(
                (ok?.rows ?? []).map((z) => [schluessel(z), z.direction]),
            ) as Record<string, Richtung>,
        [ok],
    );
    const [richtung, setRichtung] = useState<Record<string, Richtung>>(vorgabe);
    useEffect(() => setRichtung(vorgabe), [vorgabe]);
    const [filter, setFilter] = useState('alle');
    const [offen, setOffen] = useState<string | null>(null);
    const [passwort, setPasswort] = useState('');
    const [sending, setSending] = useState(false);
    const holen = useForm({ bestaetigung: '', passwort: '' });

    const zeilen = ok?.rows ?? [];
    const sichtbar = zeilen.filter(
        FILTER.find((f) => f.wert === filter)?.passt ?? (() => true),
    );
    const gewaehlt = zeilen.filter(
        (z) => (richtung[schluessel(z)] ?? 'skip') !== 'skip',
    );
    const nachDort = gewaehlt.filter(
        (z) => richtung[schluessel(z)] === 'push',
    ).length;
    const vonDort = gewaehlt.length - nachDort;
    const alle = (r: Richtung) =>
        setRichtung((alt) => {
            const n = { ...alt };
            for (const z of sichtbar) {
                const erlaubt =
                    r === 'skip' ||
                    (r === 'push' && ok?.push && z.here !== null) ||
                    (r === 'pull' && ok?.pull && z.there !== null);
                if (erlaubt) {
                    n[schluessel(z)] = r;
                }
            }
            return n;
        });
    const synchronisieren = async () => {
        if (
            !(await confirmAction(
                `${nachDort} nach «${peer.name}» senden und ${vonDort} von «${peer.name}» übernehmen? Fehlendes wird angelegt, Unterschiedliches als neue Revision aktualisiert; gelöscht wird nichts.${vonDort > 0 ? ' Vor dem Übernehmen wird hier automatisch gesichert.' : ''}`,
            ))
        ) {
            return;
        }
        setSending(true);
        router.post(
            `/admin/system/sync/gegenstellen/${peer.id}/synchronisieren`,
            {
                eintraege: gewaehlt.map((z) => ({
                    typ: z.type,
                    id: z.id,
                    richtung: richtung[schluessel(z)],
                })),
                passwort,
            },
            { preserveScroll: true, onFinish: () => setSending(false) },
        );
    };

    return (
        <section className="mt-10">
            <h2 className="text-lg font-semibold">«{peer.name}»</h2>

            <h3 className="mt-4 font-semibold">Vergleich</h3>
            {vergleich === null ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Seite neu laden, um zu vergleichen.
                </p>
            ) : 'fehler' in vergleich ? (
                <p className="mt-2 text-sm text-destructive">
                    {vergleich.fehler}
                </p>
            ) : (
                <>
                    <p className="mt-2 text-sm">
                        Rolle dort:{' '}
                        <Badge variant="outline">
                            {ROLLE[vergleich.role] ?? vergleich.role}
                        </Badge>{' '}
                        {zeilen.length === 0
                            ? 'Alles gleich.'
                            : `${zeilen.length} Datensätze unterscheiden sich.`}
                    </p>
                    {darfSync && !vergleich.push && !vergleich.pull ? (
                        <p className="mt-2 rounded-md border border-warning/40 bg-warning-soft p-3 text-sm">
                            Mit «{peer.name}» ist kein Sync möglich: Dort muss
                            die Rolle passen (von dev aus «Test» oder «Master»,
                            von test aus «Master») und das Servicekonto die
                            Scopes «glossary:sync.read» bzw.
                            «glossary:sync.write» haben.
                        </p>
                    ) : darfSync && !vergleich.push ? (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Senden nach «{peer.name}» ist nicht möglich (Rolle
                            dort oder Scope «glossary:sync.write» fehlt).
                        </p>
                    ) : darfSync && !vergleich.pull ? (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Übernehmen von «{peer.name}» ist nicht möglich
                            (Rolle dort oder Scope «glossary:sync.read» fehlt).
                        </p>
                    ) : null}
                    {darfSync && vergleich.push && !vergleich.publish ? (
                        <p className="mt-2 text-sm text-muted-foreground">
                            Ohne Scope «glossary:sync.publish» kommen Begriffe
                            dort als Entwurf an, auch wenn sie hier
                            veröffentlicht sind.
                        </p>
                    ) : null}

                    {zeilen.length > 0 ? (
                        <>
                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                {FILTER.map((f) => (
                                    <Button
                                        key={f.wert}
                                        size="sm"
                                        variant={
                                            filter === f.wert
                                                ? 'default'
                                                : 'outline'
                                        }
                                        aria-pressed={filter === f.wert}
                                        onClick={() => setFilter(f.wert)}
                                    >
                                        {f.label} (
                                        {zeilen.filter(f.passt).length})
                                    </Button>
                                ))}
                                {darfSync ? (
                                    <span className="ml-auto flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={!vergleich.pull}
                                            onClick={() => alle('pull')}
                                        >
                                            Alle ←
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => alle('skip')}
                                        >
                                            Alle –
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={!vergleich.push}
                                            onClick={() => alle('push')}
                                        >
                                            Alle →
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setRichtung(vorgabe)}
                                        >
                                            Vorgabe
                                        </Button>
                                    </span>
                                ) : null}
                            </div>
                            <div className="mt-3 overflow-x-auto rounded-lg border border-border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted text-left">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                Datensatz
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Zustand
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                hier geändert
                                            </th>
                                            <th className="px-3 py-2 text-center font-medium">
                                                Richtung
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                «{peer.name}» geändert
                                            </th>
                                            <th className="px-3 py-2">
                                                <span className="sr-only">
                                                    Details
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {sichtbar.map((z) => {
                                            const k = schluessel(z);
                                            return (
                                                <Fragment key={k}>
                                                    <tr
                                                        className={`border-t border-border align-middle ${z.state === 'conflict' ? 'bg-destructive/10' : ''}`}
                                                    >
                                                        <td className="px-3 py-2">
                                                            {z.type !==
                                                            'term' ? (
                                                                <span className="mr-1 text-xs text-muted-foreground">
                                                                    {
                                                                        TYP[
                                                                            z
                                                                                .type
                                                                        ]
                                                                    }
                                                                </span>
                                                            ) : null}
                                                            <span translate="no">
                                                                {z.title}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2 whitespace-nowrap">
                                                            <span
                                                                className={
                                                                    z.state ===
                                                                    'conflict'
                                                                        ? 'font-medium text-destructive'
                                                                        : ''
                                                                }
                                                            >
                                                                {ZUSTAND[
                                                                    z.state
                                                                ] ?? z.state}
                                                            </span>
                                                            {z.synced_before &&
                                                            [
                                                                'only_here',
                                                                'only_there',
                                                            ].includes(
                                                                z.state,
                                                            ) ? (
                                                                <span className="block text-xs text-muted-foreground">
                                                                    war schon
                                                                    synchronisiert
                                                                    – auf der
                                                                    anderen
                                                                    Seite
                                                                    gelöscht?
                                                                </span>
                                                            ) : null}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Zeitpunkt
                                                                seite={z.here}
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2 text-center">
                                                            {darfSync ? (
                                                                <RichtungWahl
                                                                    wert={
                                                                        richtung[
                                                                            k
                                                                        ] ??
                                                                        'skip'
                                                                    }
                                                                    push={
                                                                        vergleich.push
                                                                    }
                                                                    pull={
                                                                        vergleich.pull
                                                                    }
                                                                    zeile={z}
                                                                    peerName={
                                                                        peer.name
                                                                    }
                                                                    onChange={(
                                                                        r,
                                                                    ) =>
                                                                        setRichtung(
                                                                            (
                                                                                alt,
                                                                            ) => ({
                                                                                ...alt,
                                                                                [k]: r,
                                                                            }),
                                                                        )
                                                                    }
                                                                />
                                                            ) : null}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Zeitpunkt
                                                                seite={z.there}
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2 text-right">
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                aria-expanded={
                                                                    offen === k
                                                                }
                                                                onClick={() =>
                                                                    setOffen(
                                                                        offen ===
                                                                            k
                                                                            ? null
                                                                            : k,
                                                                    )
                                                                }
                                                            >
                                                                {offen === k
                                                                    ? 'Schliessen'
                                                                    : 'Unterschiede'}
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                    {offen === k ? (
                                                        <tr className="border-t border-border bg-muted/40">
                                                            <td
                                                                colSpan={6}
                                                                className="px-3 py-3"
                                                            >
                                                                <Unterschiede
                                                                    peerId={
                                                                        peer.id
                                                                    }
                                                                    peerName={
                                                                        peer.name
                                                                    }
                                                                    zeile={z}
                                                                />
                                                            </td>
                                                        </tr>
                                                    ) : null}
                                                </Fragment>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                            {darfSync && (vergleich.push || vergleich.pull) ? (
                                <div className="mt-4 flex flex-wrap items-end gap-3">
                                    {vonDort > 0 && passwortNoetig ? (
                                        <div>
                                            <Label htmlFor="sync-passwort">
                                                Passwort für die Sicherung
                                            </Label>
                                            <Input
                                                id="sync-passwort"
                                                type="password"
                                                value={passwort}
                                                onChange={(e) =>
                                                    setPasswort(e.target.value)
                                                }
                                            />
                                            <InputError
                                                message={errors.passwort}
                                            />
                                        </div>
                                    ) : null}
                                    <p className="text-sm text-muted-foreground">
                                        {nachDort} → nach «{peer.name}»,{' '}
                                        {vonDort} ← von «{peer.name}»
                                    </p>
                                    <Button
                                        disabled={
                                            sending || gewaehlt.length === 0
                                        }
                                        onClick={synchronisieren}
                                    >
                                        {sending
                                            ? 'Synchronisiert …'
                                            : 'Synchronisieren'}
                                    </Button>
                                </div>
                            ) : null}
                            <InputError message={errors.sync} />
                        </>
                    ) : null}
                </>
            )}

            {darfSync ? (
                <details className="mt-10 rounded-lg border border-border p-4">
                    <summary className="cursor-pointer text-sm font-medium">
                        Ganze Instanz ersetzen
                    </summary>
                    <form
                        className="mt-3 grid gap-3 sm:grid-cols-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            holen.post(
                                `/admin/system/sync/gegenstellen/${peer.id}/holen`,
                            );
                        }}
                    >
                        <p className="text-sm sm:col-span-3">
                            <strong>Ersetzen</strong> löscht alle Inhalte dieser
                            Instanz und übernimmt den ganzen Stand von «
                            {peer.name}» (vorher automatische Sicherung).
                            Konten, Einstellungen, Servicekonten und
                            Gegenstellen bleiben. Für den Alltag den Sync oben
                            verwenden.
                            {eigeneRolle === 'test'
                                ? ' Achtung: Ersetzen ersetzt den Stand der laufenden Beta-Prüfung auf test.'
                                : ''}
                        </p>
                        <div>
                            <Label htmlFor="bestaetigung">
                                Zur Bestätigung «HOLEN» eingeben
                            </Label>
                            <Input
                                id="bestaetigung"
                                value={holen.data.bestaetigung}
                                onChange={(e) =>
                                    holen.setData(
                                        'bestaetigung',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError message={holen.errors.bestaetigung} />
                        </div>
                        {passwortNoetig ? (
                            <div>
                                <Label htmlFor="passwort">
                                    Passwort für die Sicherung
                                </Label>
                                <Input
                                    id="passwort"
                                    type="password"
                                    value={holen.data.passwort}
                                    onChange={(e) =>
                                        holen.setData(
                                            'passwort',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError message={holen.errors.passwort} />
                            </div>
                        ) : null}
                        <div className="flex items-end">
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={holen.processing}
                            >
                                {holen.processing ? 'Ersetzt …' : 'Ersetzen'}
                            </Button>
                        </div>
                        <InputError message={errors.holen} />
                    </form>
                </details>
            ) : null}
        </section>
    );
}
