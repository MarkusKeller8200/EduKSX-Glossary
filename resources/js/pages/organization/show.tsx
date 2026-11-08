import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { confirmAction } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Organisation = {
    name: string;
    rolle: string;
    gesperrt: boolean;
    admin: boolean;
    zwei_faktor: boolean;
    gegruendet: string | null;
};

type Mitglied = {
    id: number;
    name: string;
    email: string | null;
    rolle: string;
    admin: boolean;
    gesperrt: boolean;
    ich: boolean;
    seit: string | null;
};

type Einladung = {
    id: number;
    email: string;
    gueltig_bis: string;
    von: string | null;
};

type MeineEinladung = { id: number; organisation: string; gueltig_bis: string };

type Anpassung = { slug: string; titel: string; geaendert: string | null };

type Vorschlag = {
    id: number;
    art: string;
    titel: string;
    slug: string | null;
    von: string;
    begruendung: string | null;
    aenderungen: { feld: string; wert: string }[];
    eingereicht: string | null;
};

type Props = {
    organisation: Organisation | null;
    mitglieder: Mitglied[];
    einladungen: Einladung[];
    anpassungen: Anpassung[];
    servicekonten: number;
    vorschlaege: Vorschlag[];
    meine_einladungen: MeineEinladung[];
    darf_gruenden: boolean;
};

const opts = { preserveScroll: true } as const;

/** «Meine Organisation» (#148, DEC-025): gründen, einladen, Mitglieder verwalten, austreten. */
export default function OrganizationShow(props: Props) {
    const { organisation: org } = props;
    // Meldungen (flash) zeigt das Layout; Abweisungen der Regeln kommen als Fehler «organisation»
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;

    return (
        <>
            <Head title="Meine Organisation" />
            <h1 className="text-3xl font-semibold tracking-tight">
                {org ? org.name : 'Meine Organisation'}
            </h1>
            {org ? (
                <p className="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <Badge variant={org.admin ? 'default' : 'secondary'}>
                        {org.rolle}
                    </Badge>
                    {org.gesperrt ? (
                        <Badge variant="destructive">gesperrt</Badge>
                    ) : null}
                    {org.gegruendet ? `gegründet am ${org.gegruendet}` : null}
                </p>
            ) : null}
            <p className="mt-3 max-w-prose text-sm text-muted-foreground">
                Mitglieder einer Organisation sehen die Begriffe in der
                Darstellung ihrer Organisation. Die Organisations-Admins legen
                diese auf der Seite eines Begriffs fest. Die persönliche
                Darstellung geht vor; öffentlich bleibt immer die gemeinsame
                Form.
            </p>

            <InputError className="mt-4" message={errors.organisation} />

            {props.meine_einladungen.length > 0 ? (
                <MeineEinladungen
                    einladungen={props.meine_einladungen}
                    mitglied={org !== null}
                />
            ) : null}

            {org === null ? (
                <Gruenden darf={props.darf_gruenden} />
            ) : org.gesperrt ? (
                <Abschnitt titel="Gesperrt">
                    <p className="text-sm">
                        Ein Organisations-Admin hat Ihre Mitgliedschaft
                        gesperrt. Sie sehen die gemeinsame Darstellung.
                    </p>
                </Abschnitt>
            ) : (
                <>
                    {org.admin && !org.zwei_faktor ? (
                        <p className="mt-6 max-w-prose rounded-lg border border-border bg-muted/40 p-3 text-sm">
                            Für die Verwaltung der Organisation braucht es den
                            zweiten Faktor.{' '}
                            <Link
                                href="/konto/zwei-faktor"
                                className="text-link underline underline-offset-4"
                            >
                                Jetzt einrichten
                            </Link>
                        </p>
                    ) : null}
                    <Mitglieder
                        mitglieder={props.mitglieder}
                        admin={org.admin}
                    />
                    {org.admin ? (
                        <Vorschlaege vorschlaege={props.vorschlaege} />
                    ) : null}
                    {org.admin ? (
                        <Einladungen einladungen={props.einladungen} />
                    ) : null}
                    <Anpassungen anpassungen={props.anpassungen} />
                    {org.admin ? (
                        <Verwaltung
                            name={org.name}
                            servicekonten={props.servicekonten}
                        />
                    ) : null}
                    <Austreten />
                </>
            )}
        </>
    );
}

function Gruenden({ darf }: { darf: boolean }) {
    const form = useForm({ name: '' });
    if (!darf) {
        return (
            <Abschnitt titel="Organisation gründen">
                <p className="text-sm text-muted-foreground">
                    Ihr Konto gehört keiner Organisation an. Gründen können
                    Konten mit der Rolle «Benutzer» oder höher.
                </p>
            </Abschnitt>
        );
    }
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/organisation', opts);
    };

    return (
        <Abschnitt titel="Organisation gründen">
            <p className="text-sm text-muted-foreground">
                Optional. Sie werden Organisations-Admin, können andere per
                E-Mail einladen und eine gemeinsame Darstellung der Begriffe
                festlegen. Für die Verwaltung ist der zweite Faktor Pflicht.
            </p>
            <form
                onSubmit={submit}
                className="mt-3 flex flex-wrap items-end gap-2"
            >
                <div className="grid gap-1.5">
                    <Label htmlFor="org-name">Name der Organisation</Label>
                    <Input
                        id="org-name"
                        className="w-72"
                        maxLength={120}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                    />
                </div>
                <Button type="submit" disabled={form.processing}>
                    Gründen
                </Button>
            </form>
            <InputError message={form.errors.name} />
        </Abschnitt>
    );
}

function MeineEinladungen({
    einladungen,
    mitglied,
}: {
    einladungen: MeineEinladung[];
    mitglied: boolean;
}) {
    return (
        <Abschnitt titel="Einladungen an mich">
            {mitglied ? (
                <p className="mb-2 text-sm text-muted-foreground">
                    Ein Konto gehört höchstens einer Organisation an. Zum
                    Annehmen zuerst die bisherige verlassen.
                </p>
            ) : null}
            <ul className="grid gap-2">
                {einladungen.map((e) => (
                    <li
                        key={e.id}
                        className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border p-3 text-sm"
                    >
                        <span>
                            <span className="font-medium">
                                {e.organisation}
                            </span>{' '}
                            <span className="text-muted-foreground">
                                (gültig bis {e.gueltig_bis})
                            </span>
                        </span>
                        <span className="flex gap-2">
                            <Button
                                size="sm"
                                disabled={mitglied}
                                onClick={() =>
                                    router.post(
                                        `/organisation/einladungen/${e.id}/annehmen`,
                                        {},
                                        opts,
                                    )
                                }
                            >
                                Annehmen
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/organisation/einladungen/${e.id}/ablehnen`,
                                        {},
                                        opts,
                                    )
                                }
                            >
                                Ablehnen
                            </Button>
                        </span>
                    </li>
                ))}
            </ul>
        </Abschnitt>
    );
}

function Mitglieder({
    mitglieder,
    admin,
}: {
    mitglieder: Mitglied[];
    admin: boolean;
}) {
    const aktion = async (
        m: Mitglied,
        art: 'sperren' | 'entsperren' | 'admin' | 'mitglied' | 'entfernen',
    ) => {
        if (art === 'entfernen') {
            const ok = await confirmAction(
                `${m.name} aus der Organisation entfernen? Das Konto bleibt bestehen.`,
                { confirmLabel: 'Entfernen', destructive: true },
            );
            if (ok) {
                router.delete(`/organisation/mitglieder/${m.id}`, opts);
            }

            return;
        }
        if (art === 'admin' || art === 'mitglied') {
            router.put(
                `/organisation/mitglieder/${m.id}/rolle`,
                { admin: art === 'admin' },
                opts,
            );

            return;
        }
        router.post(`/organisation/mitglieder/${m.id}/${art}`, {}, opts);
    };

    return (
        <Abschnitt titel={`Mitglieder (${mitglieder.length})`}>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="text-left text-muted-foreground">
                        <tr>
                            <th className="py-1 pr-4 font-medium">Name</th>
                            {admin ? (
                                <th className="py-1 pr-4 font-medium">
                                    E-Mail
                                </th>
                            ) : null}
                            <th className="py-1 pr-4 font-medium">Rolle</th>
                            <th className="py-1 pr-4 font-medium">seit</th>
                            {admin ? (
                                <th className="py-1 font-medium">
                                    <span className="sr-only">Aktionen</span>
                                </th>
                            ) : null}
                        </tr>
                    </thead>
                    <tbody>
                        {mitglieder.map((m) => (
                            <tr key={m.id} className="border-t border-border">
                                <td className="py-2 pr-4">
                                    {m.name}
                                    {m.ich ? (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            (ich)
                                        </span>
                                    ) : null}
                                </td>
                                {admin ? (
                                    <td className="py-2 pr-4 break-all">
                                        {m.email}
                                    </td>
                                ) : null}
                                <td className="py-2 pr-4">
                                    {m.rolle}
                                    {m.gesperrt ? (
                                        <Badge
                                            variant="destructive"
                                            className="ml-2"
                                        >
                                            gesperrt
                                        </Badge>
                                    ) : null}
                                </td>
                                <td className="py-2 pr-4 whitespace-nowrap">
                                    {m.seit ?? '–'}
                                </td>
                                {admin ? (
                                    <td className="py-2">
                                        <div className="flex flex-wrap justify-end gap-1">
                                            {m.admin ? (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        aktion(m, 'mitglied')
                                                    }
                                                >
                                                    Admin entziehen
                                                </Button>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={m.gesperrt}
                                                    onClick={() =>
                                                        aktion(m, 'admin')
                                                    }
                                                >
                                                    Zum Admin
                                                </Button>
                                            )}
                                            {m.ich ? null : (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            aktion(
                                                                m,
                                                                m.gesperrt
                                                                    ? 'entsperren'
                                                                    : 'sperren',
                                                            )
                                                        }
                                                    >
                                                        {m.gesperrt
                                                            ? 'Entsperren'
                                                            : 'Sperren'}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            aktion(
                                                                m,
                                                                'entfernen',
                                                            )
                                                        }
                                                    >
                                                        Entfernen
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                ) : null}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Abschnitt>
    );
}

/** Vorschläge der Mitglieder (#148): erst nach Bestätigung durch einen Organisations-Admin in die Moderation. */
function Vorschlaege({ vorschlaege }: { vorschlaege: Vorschlag[] }) {
    return (
        <Abschnitt titel={`Vorschläge zur Bestätigung (${vorschlaege.length})`}>
            <p className="text-sm text-muted-foreground">
                Vorschläge von Mitgliedern gehen erst nach Ihrer Bestätigung an
                die Redaktion. Ihre eigenen Vorschläge gehen direkt dorthin.
            </p>
            {vorschlaege.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Keine offenen Vorschläge.
                </p>
            ) : (
                <ul className="mt-3 grid gap-3">
                    {vorschlaege.map((v) => (
                        <VorschlagKarte key={v.id} v={v} />
                    ))}
                </ul>
            )}
        </Abschnitt>
    );
}

function VorschlagKarte({ v }: { v: Vorschlag }) {
    const form = useForm({ grund: '' });
    const [ablehnen, setAblehnen] = useState(false);

    return (
        <li className="rounded-lg border border-border p-3 text-sm">
            <p>
                <span className="text-muted-foreground">
                    Nr. {v.id} · {v.art}:
                </span>{' '}
                {v.slug ? (
                    <Link
                        href={`/begriffe/${v.slug}`}
                        className="font-medium text-link underline underline-offset-4"
                    >
                        {v.titel}
                    </Link>
                ) : (
                    <span className="font-medium">{v.titel}</span>
                )}{' '}
                <span className="text-muted-foreground">
                    – von {v.von}
                    {v.eingereicht ? `, ${v.eingereicht}` : ''}
                </span>
            </p>
            <dl className="mt-2 grid gap-x-4 gap-y-1 sm:grid-cols-[12rem_1fr]">
                {v.aenderungen.map((a) => (
                    <Fragment key={a.feld}>
                        <dt className="text-muted-foreground">{a.feld}</dt>
                        <dd className="whitespace-pre-line">{a.wert || '–'}</dd>
                    </Fragment>
                ))}
                {v.begruendung ? (
                    <>
                        <dt className="text-muted-foreground">Begründung</dt>
                        <dd className="whitespace-pre-line">{v.begruendung}</dd>
                    </>
                ) : null}
            </dl>
            <div className="mt-3 flex flex-wrap gap-2">
                <Button
                    size="sm"
                    onClick={() =>
                        router.post(
                            `/organisation/vorschlaege/${v.id}/bestaetigen`,
                            {},
                            opts,
                        )
                    }
                >
                    Bestätigen
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setAblehnen(!ablehnen)}
                >
                    Ablehnen …
                </Button>
            </div>
            {ablehnen ? (
                <form
                    className="mt-3 grid gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(
                            `/organisation/vorschlaege/${v.id}/ablehnen`,
                            opts,
                        );
                    }}
                >
                    <Label htmlFor={`grund-${v.id}`}>
                        Begründung (sieht die einreichende Person)
                    </Label>
                    <Textarea
                        id={`grund-${v.id}`}
                        rows={2}
                        value={form.data.grund}
                        onChange={(e) => form.setData('grund', e.target.value)}
                        required
                    />
                    <InputError message={form.errors.grund} />
                    <div>
                        <Button
                            type="submit"
                            size="sm"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            Ablehnen
                        </Button>
                    </div>
                </form>
            ) : null}
        </li>
    );
}

function Einladungen({ einladungen }: { einladungen: Einladung[] }) {
    const form = useForm({ adressen: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/organisation/einladungen', {
            ...opts,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Abschnitt titel="Einladen">
            <form onSubmit={submit} className="grid gap-2">
                <Label htmlFor="adressen">E-Mail-Adressen</Label>
                <Textarea
                    id="adressen"
                    rows={3}
                    value={form.data.adressen}
                    onChange={(e) => form.setData('adressen', e.target.value)}
                    placeholder="anna@schule.ch; ben@schule.ch"
                    required
                />
                <p className="text-xs text-muted-foreground">
                    Mehrere mit «;» oder «,» trennen. Die Einladung gilt 14
                    Tage; die eingeladene Person legt mit der Adresse ein Konto
                    an oder meldet sich an und nimmt die Einladung hier an.
                </p>
                <InputError message={form.errors.adressen} />
                <div>
                    <Button type="submit" disabled={form.processing}>
                        Einladen
                    </Button>
                </div>
            </form>
            {einladungen.length > 0 ? (
                <>
                    <h3 className="mt-6 text-sm font-semibold">
                        Offene Einladungen ({einladungen.length})
                    </h3>
                    <ul className="mt-2 grid gap-1 text-sm">
                        {einladungen.map((e) => (
                            <li
                                key={e.id}
                                className="flex flex-wrap items-center justify-between gap-2 border-t border-border py-1.5"
                            >
                                <span className="break-all">
                                    {e.email}{' '}
                                    <span className="text-muted-foreground">
                                        – bis {e.gueltig_bis}
                                        {e.von ? `, von ${e.von}` : ''}
                                    </span>
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.delete(
                                            `/organisation/einladungen/${e.id}`,
                                            opts,
                                        )
                                    }
                                >
                                    Zurückziehen
                                </Button>
                            </li>
                        ))}
                    </ul>
                </>
            ) : null}
        </Abschnitt>
    );
}

function Anpassungen({ anpassungen }: { anpassungen: Anpassung[] }) {
    return (
        <Abschnitt titel={`Angepasste Begriffe (${anpassungen.length})`}>
            {anpassungen.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Noch keine. Organisations-Admins passen einen Begriff auf
                    seiner Seite unter «Darstellung der Organisation» an.
                </p>
            ) : (
                <ul className="grid gap-1 text-sm">
                    {anpassungen.map((a) => (
                        <li key={a.slug}>
                            <Link
                                href={`/begriffe/${a.slug}`}
                                className="text-link underline underline-offset-4"
                            >
                                {a.titel}
                            </Link>
                            {a.geaendert ? (
                                <span className="text-muted-foreground">
                                    {' '}
                                    – {a.geaendert}
                                </span>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
        </Abschnitt>
    );
}

function Verwaltung({
    name,
    servicekonten,
}: {
    name: string;
    servicekonten: number;
}) {
    const form = useForm({ name });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/organisation', opts);
    };
    const loeschen = async () => {
        const ok = await confirmAction(
            `Organisation «${name}» löschen? Darstellung, Einladungen und Servicekonten der Organisation werden gelöscht; die Konten der Mitglieder bleiben bestehen.`,
            { confirmLabel: 'Organisation löschen', destructive: true },
        );
        if (ok) {
            router.post('/organisation/loeschen', {}, opts);
        }
    };

    return (
        <Abschnitt titel="Verwaltung">
            <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="org-rename">Name</Label>
                    <Input
                        id="org-rename"
                        className="w-72"
                        maxLength={120}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                    />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                >
                    Umbenennen
                </Button>
            </form>
            <InputError message={form.errors.name} />

            <h3 className="mt-6 text-sm font-semibold">API-Zugang</h3>
            <p className="mt-1 text-sm text-muted-foreground">
                Servicekonto mit Scope «read-org» anlegen: Das Token liefert die
                Begriffe in der Darstellung dieser Organisation (
                <code>/api/v1/org/terms</code>). Bisher {servicekonten}{' '}
                Servicekonto/-konten der Organisation.
            </p>
            <Button asChild variant="outline" className="mt-2">
                <a href="/admin/servicekonten">Servicekonten verwalten</a>
            </Button>

            <h3 className="mt-6 text-sm font-semibold">Organisation löschen</h3>
            <Button variant="destructive" className="mt-2" onClick={loeschen}>
                Organisation löschen
            </Button>
        </Abschnitt>
    );
}

function Austreten() {
    const austreten = async () => {
        const ok = await confirmAction(
            'Organisation verlassen? Sie sehen danach die gemeinsame Darstellung.',
            { confirmLabel: 'Austreten', destructive: true },
        );
        if (ok) {
            router.post('/organisation/austreten', {}, opts);
        }
    };

    return (
        <Abschnitt titel="Austreten">
            <p className="text-sm text-muted-foreground">
                Der letzte Organisations-Admin kann erst austreten, wenn ein
                anderes Mitglied Admin ist.
            </p>
            <Button variant="outline" className="mt-2" onClick={austreten}>
                Organisation verlassen
            </Button>
        </Abschnitt>
    );
}

function Abschnitt({
    titel,
    children,
}: {
    titel: string;
    children: ReactNode;
}) {
    return (
        <section className="mt-8 max-w-4xl border-t border-border pt-6">
            <h2 className="text-lg font-semibold">{titel}</h2>
            <div className="mt-3">{children}</div>
        </section>
    );
}
