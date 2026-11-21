import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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

type Eintrag = { id: string; title: string; state: string };
type Listeneintrag = {
    list: string;
    key: string;
    title: string;
    state: string;
};

type Vergleich =
    | {
          role: string;
          publish: boolean;
          upload: boolean;
          blocked: boolean;
          terms: Eintrag[];
          lists: Listeneintrag[];
      }
    | { fehler: string };

const ZUSTAND: Record<string, string> = {
    new: 'neu hier',
    local: 'hier geändert',
    remote: 'dort geändert – zuerst holen',
    conflict: 'Konflikt – zuerst holen',
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

/** Entwicklungs-Sync (#478): Gegenstellen, Holen, Vergleichen und Hochladen. */
export default function Sync({
    rolle,
    darfHolen,
    darfHochladen,
    passwortNoetig,
    gegenstellen,
    aktiv,
    vergleich,
}: {
    rolle: { wert: string; label: string; quelle: string };
    darfHolen: boolean;
    darfHochladen: boolean;
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
                Inhalte auf dev pflegen und nach test und prod hochladen;
                «Holen» bringt diese Instanz auf den Stand der Gegenstelle (ohne
                Konten, Personendaten anonymisiert; Einstellungen, Servicekonten
                und Gegenstellen bleiben).
            </p>
            <p className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                Rolle dieser Instanz:{' '}
                <Badge variant="outline">{rolle.label}</Badge>
                <span className="text-muted-foreground">
                    ({rolle.quelle}; ändern unter Einstellungen bzw.{' '}
                    <code>GLOSSARY_SYNC_ROLE</code>)
                </span>
            </p>
            {!darfHolen ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Als «{rolle.label}» holt diese Instanz nicht und lädt nicht
                    hoch. Sie kann Gegenstelle für dev bzw. test sein
                    (Servicekonto mit den Scopes «glossary:sync.…»).
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
                                    Geholt {datum(g.geholt)} · hochgeladen{' '}
                                    {datum(g.hochgeladen)}
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
                    darfHolen={darfHolen}
                    darfHochladen={darfHochladen}
                    passwortNoetig={passwortNoetig}
                    eigeneRolle={rolle.wert}
                    errors={errors}
                />
            ) : null}
        </>
    );
}

function GegenstelleDetail({
    peer,
    vergleich,
    darfHolen,
    darfHochladen,
    passwortNoetig,
    eigeneRolle,
    errors,
}: {
    peer: Gegenstelle;
    vergleich: Vergleich | null;
    darfHolen: boolean;
    darfHochladen: boolean;
    passwortNoetig: boolean;
    eigeneRolle: string;
    errors: Record<string, string>;
}) {
    const holen = useForm({ bestaetigung: '', passwort: '' });
    const [auswahl, setAuswahl] = useState<Set<string>>(new Set());
    const [veroeffentlichen, setVeroeffentlichen] = useState(false);
    const [sending, setSending] = useState(false);
    const ok =
        vergleich !== null && !('fehler' in vergleich) ? vergleich : null;
    const bereit = useMemo(
        () =>
            ok
                ? ok.terms.filter((t) => ['new', 'local'].includes(t.state))
                : [],
        [ok],
    );
    const listenBereit = ok
        ? ok.lists.filter((l) => ['new', 'local'].includes(l.state))
        : [];
    const hochladen = () => {
        setSending(true);
        router.post(
            `/admin/system/sync/gegenstellen/${peer.id}/hochladen`,
            { ids: [...auswahl], veroeffentlichen },
            {
                preserveScroll: true,
                onFinish: () => setSending(false),
                onSuccess: () => setAuswahl(new Set()),
            },
        );
    };

    return (
        <section className="mt-10">
            <h2 className="text-lg font-semibold">«{peer.name}»</h2>

            {darfHolen ? (
                <form
                    className="mt-3 grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        holen.post(
                            `/admin/system/sync/gegenstellen/${peer.id}/holen`,
                        );
                    }}
                >
                    <p className="text-sm sm:col-span-3">
                        <strong>Holen</strong> ersetzt alle Inhalte dieser
                        Instanz durch den Stand von «{peer.name}» (vorher
                        automatische Sicherung). Konten, Einstellungen,
                        Servicekonten und Gegenstellen bleiben.
                        {eigeneRolle === 'test'
                            ? ' Achtung: Holen ersetzt den Stand der laufenden Beta-Prüfung auf test.'
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
                                holen.setData('bestaetigung', e.target.value)
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
                                    holen.setData('passwort', e.target.value)
                                }
                            />
                            <InputError message={holen.errors.passwort} />
                        </div>
                    ) : null}
                    <div className="flex items-end">
                        <Button type="submit" disabled={holen.processing}>
                            {holen.processing ? 'Holt …' : 'Holen'}
                        </Button>
                    </div>
                    <InputError message={errors.holen} />
                </form>
            ) : null}

            <h3 className="mt-6 font-semibold">Vergleich</h3>
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
                        {vergleich.terms.length === 0 &&
                        vergleich.lists.length === 0
                            ? 'Alles gleich.'
                            : `${vergleich.terms.length} Begriffe und ${vergleich.lists.length} Listeneinträge unterscheiden sich.`}
                    </p>
                    {vergleich.blocked ? (
                        <p className="mt-2 rounded-md border border-warning/40 bg-warning-soft p-3 text-sm">
                            Die Gegenstelle hat sich seit dem letzten Holen bzw.
                            Hochladen geändert. Zuerst «Holen», dann hochladen –
                            sonst würden Änderungen dort überschrieben.
                        </p>
                    ) : null}
                    <ul className="mt-3 space-y-1 text-sm">
                        {vergleich.terms.map((t) => {
                            const waehlbar =
                                darfHochladen &&
                                vergleich.upload &&
                                !vergleich.blocked &&
                                ['new', 'local'].includes(t.state);
                            return (
                                <li
                                    key={t.id}
                                    className="flex items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        aria-label={`${t.title} hochladen`}
                                        disabled={!waehlbar}
                                        checked={auswahl.has(t.id)}
                                        onChange={(e) =>
                                            setAuswahl((alt) => {
                                                const n = new Set(alt);
                                                if (e.target.checked) {
                                                    n.add(t.id);
                                                } else {
                                                    n.delete(t.id);
                                                }
                                                return n;
                                            })
                                        }
                                    />
                                    <span>{t.title}</span>
                                    <span className="text-muted-foreground">
                                        {ZUSTAND[t.state] ?? t.state}
                                    </span>
                                </li>
                            );
                        })}
                        {vergleich.lists.map((l) => (
                            <li
                                key={`${l.list}-${l.key}`}
                                className="text-muted-foreground"
                            >
                                {l.list === 'categories'
                                    ? 'Kategorie'
                                    : 'Herkunft'}{' '}
                                «{l.title}»: {ZUSTAND[l.state] ?? l.state}
                            </li>
                        ))}
                    </ul>
                    {darfHochladen &&
                    vergleich.upload &&
                    !vergleich.blocked &&
                    (bereit.length > 0 || listenBereit.length > 0) ? (
                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setAuswahl(new Set(bereit.map((b) => b.id)))
                                }
                            >
                                Alle bereit wählen ({bereit.length})
                            </Button>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="radio"
                                    name="art"
                                    checked={!veroeffentlichen}
                                    onChange={() => setVeroeffentlichen(false)}
                                />
                                als Entwurf
                            </label>
                            <label
                                className={`flex items-center gap-2 text-sm ${vergleich.publish ? '' : 'text-muted-foreground'}`}
                                title={
                                    vergleich.publish
                                        ? undefined
                                        : 'Token ohne Scope «glossary:sync.publish»'
                                }
                            >
                                <input
                                    type="radio"
                                    name="art"
                                    disabled={!vergleich.publish}
                                    checked={veroeffentlichen}
                                    onChange={() => setVeroeffentlichen(true)}
                                />
                                direkt veröffentlichen
                            </label>
                            <Button
                                disabled={
                                    sending ||
                                    (auswahl.size === 0 &&
                                        listenBereit.length === 0)
                                }
                                onClick={async () => {
                                    if (
                                        await confirmAction(
                                            `${auswahl.size} Begriffe${listenBereit.length > 0 ? ` und ${listenBereit.length} Listeneinträge` : ''} nach «${peer.name}» hochladen${veroeffentlichen ? ' und veröffentlichen' : ' (Entwurf)'}?`,
                                        )
                                    ) {
                                        hochladen();
                                    }
                                }}
                            >
                                Hochladen
                            </Button>
                        </div>
                    ) : null}
                    <InputError message={errors.hochladen} />
                </>
            )}
        </section>
    );
}
