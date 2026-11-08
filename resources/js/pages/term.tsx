import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ListBadges, StatusBadge } from '@/components/tags';

type Meine = {
    abbreviation_override: string;
    name_override: string;
    short_description_override: string;
    description_override: string;
    aliases_override: string;
};

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
    mehrdeutig: boolean;
    verlauf: { revision: number; am: string | null }[];
    permalink: string;
    // nur angemeldet (DEC-020): persönliche Darstellung des eigenen Kontos
    persoenlich?: boolean;
    gemeinsam?: {
        abkuerzung: string;
        bezeichnung: string;
        kurz: string | null;
        beschreibung: string | null;
    };
    meine?: Meine;
    // #148: Meine Darstellung wartet auf Freigabe oder ist ohne Organisation nicht möglich
    meine_status?: 'freigegeben' | 'wartet' | null;
    meine_moeglich?: boolean;
    // nur für aktive Mitglieder einer Organisation (#148)
    organisation?: {
        name: string;
        gesetzt: boolean;
        bearbeiten: boolean;
        werte: Meine | null;
    };
};

/** Detailseite eines veröffentlichten Begriffs (#24, PUB-03/04/05/07). */
export default function Term({ begriff: b }: { begriff: Begriff }) {
    const titel = [b.abkuerzung, b.bezeichnung].filter(Boolean).join(' – ');
    const offiziell = [b.abkuerzung_offiziell, b.bezeichnung_offiziell];
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

            <article className="mt-4 max-w-prose">
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
                    <ListBadges list="kategorien" labels={b.kategorien} />
                    {b.status !== 'aktuell' ? (
                        <StatusBadge status={b.status} />
                    ) : null}
                </div>

                <Meldung />

                {(b.persoenlich || b.organisation?.gesetzt) && b.gemeinsam ? (
                    <p className="mt-3 text-sm text-muted-foreground">
                        {b.persoenlich ? (
                            <Badge variant="secondary">Meine Darstellung</Badge>
                        ) : (
                            <Badge variant="secondary">
                                Darstellung «{b.organisation?.name}»
                            </Badge>
                        )}{' '}
                        Gemeinsame Form:{' '}
                        <span translate="no">
                            {[b.gemeinsam.abkuerzung, b.gemeinsam.bezeichnung]
                                .filter(Boolean)
                                .join(' – ')}
                        </span>
                    </p>
                ) : null}

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
                                {offiziell.filter(Boolean).join(' – ')}
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
                    <dt className="text-muted-foreground">Permalink</dt>
                    <dd className="flex flex-wrap items-center gap-2">
                        <a
                            href={b.permalink}
                            className="text-link underline underline-offset-4"
                        >
                            {b.permalink.replace(/^https?:\/\//, '')}
                        </a>
                        <KopierenKnopf text={b.permalink} />
                    </dd>
                    <dt className="text-muted-foreground">Stand</dt>
                    <dd>
                        V{b.revision}
                        {b.veroeffentlicht
                            ? `, veröffentlicht am ${b.veroeffentlicht}`
                            : ''}
                    </dd>
                    {b.verlauf.length > 1 ? (
                        <>
                            <dt className="text-muted-foreground">Verlauf</dt>
                            <dd>
                                <ol className="flex flex-wrap gap-x-3 gap-y-1">
                                    {b.verlauf.map((v) => (
                                        <li key={v.revision}>
                                            V{v.revision}
                                            {v.am ? ` (${v.am})` : ''}
                                        </li>
                                    ))}
                                </ol>
                            </dd>
                        </>
                    ) : null}
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

                {b.organisation ? (
                    <OrganisationDarstellung
                        slug={b.slug}
                        organisation={b.organisation}
                    />
                ) : null}

                {b.meine ? (
                    <MeineDarstellung
                        slug={b.slug}
                        werte={b.meine}
                        gesetzt={b.meine_status != null}
                        wartet={b.meine_status === 'wartet'}
                        admin={b.organisation?.bearbeiten === true}
                    />
                ) : b.meine_moeglich === false ? (
                    <p className="mt-10 text-sm text-muted-foreground">
                        «Meine Darstellung» (eigene Texte zu einem Begriff) ist
                        als Mitglied einer Organisation möglich –{' '}
                        <Link
                            href="/organisation"
                            className="text-link underline underline-offset-4"
                        >
                            Meine Organisation
                        </Link>
                        .
                    </p>
                ) : null}

                <p className="mt-10 text-sm">
                    Stimmt etwas nicht oder fehlt etwas?{' '}
                    <Link
                        href={`/vorschlaege/neu?begriff=${b.slug}`}
                        className="text-link underline underline-offset-4"
                    >
                        Korrektur vorschlagen
                    </Link>
                </p>
                <p className="mt-4 text-xs text-muted-foreground">
                    Erklärungen ohne amtliche Verbindlichkeit; massgebend sind
                    die genannten Quellen.
                </p>
            </article>
        </>
    );
}

/** Permalink kopieren (#154); ohne Zwischenablage (z. B. http) bleibt der Link zum Markieren. */
function KopierenKnopf({ text }: { text: string }) {
    const [kopiert, setKopiert] = useState(false);
    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        return null;
    }

    return (
        <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() =>
                navigator.clipboard.writeText(text).then(() => {
                    setKopiert(true);
                    window.setTimeout(() => setKopiert(false), 2000);
                })
            }
        >
            {kopiert ? 'Kopiert' : 'Link kopieren'}
        </Button>
    );
}

/** Meldung nach dem Speichern einer Darstellung (Seite «term» zeigt Meldungen selbst). */
function Meldung() {
    const { flash, errors } = usePage().props as {
        flash: { status?: string | null };
        errors?: Record<string, string>;
    };

    return (
        <>
            {flash.status ? (
                <p className="mt-3 text-sm font-medium" role="status">
                    {flash.status}
                </p>
            ) : null}
            <InputError className="mt-3" message={errors?.organisation} />
        </>
    );
}

/** «Meine Darstellung» (DEC-020): nur für das eigene Konto sichtbar, keine Moderation, nie öffentlich. */
function MeineDarstellung({
    slug,
    werte,
    gesetzt,
    wartet,
    admin,
}: {
    slug: string;
    werte: Meine;
    gesetzt: boolean;
    wartet: boolean;
    admin: boolean;
}) {
    const freigabe = admin
        ? 'Als Organisations-Admin gelten deine Texte sofort.'
        : 'Gilt erst, wenn ein Organisations-Admin sie freigibt; jede Änderung braucht eine neue Freigabe.';

    return (
        <Darstellung
            titel={
                wartet
                    ? 'Meine Darstellung (wartet auf Freigabe)'
                    : 'Meine Darstellung'
            }
            pfad={`/begriffe/${slug}/meine-darstellung`}
            werte={werte}
            gesetzt={gesetzt}
            hinweis={`Nur für dich sichtbar, wenn du angemeldet bist. ${freigabe} Leere Felder zeigen die Form deiner Organisation bzw. die gemeinsame. Wird nicht veröffentlicht und nicht über die API weitergegeben; mit deinem Konto gelöscht.`}
        />
    );
}

/** Darstellung der Organisation (#148): sichtbar für alle Mitglieder, bearbeiten nur Organisations-Admins. */
function OrganisationDarstellung({
    slug,
    organisation,
}: {
    slug: string;
    organisation: NonNullable<Begriff['organisation']>;
}) {
    if (!organisation.bearbeiten || organisation.werte === null) {
        return organisation.gesetzt ? (
            <p className="mt-10 text-sm text-muted-foreground">
                Angezeigt in der Darstellung der Organisation «
                {organisation.name}». Anpassen können die Organisations-Admins.
            </p>
        ) : null;
    }

    return (
        <Darstellung
            titel={`Darstellung der Organisation «${organisation.name}»`}
            pfad={`/begriffe/${slug}/organisation-darstellung`}
            werte={organisation.werte}
            gesetzt={organisation.gesetzt}
            hinweis="Sichtbar für alle aktiven Mitglieder der Organisation; die persönliche Darstellung geht vor. Leere Felder zeigen die gemeinsame Form. Nicht öffentlich; über die API nur mit einem Token der Organisation (Scope «read-org»)."
        />
    );
}

/** Formular einer angepassten Darstellung (persönlich oder Organisation). */
function Darstellung({
    titel,
    pfad,
    werte,
    gesetzt,
    hinweis,
}: {
    titel: string;
    pfad: string;
    werte: Meine;
    gesetzt: boolean;
    hinweis: string;
}) {
    const form = useForm<Meine>(werte);
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(pfad, { preserveScroll: true });
    };
    const feld = (
        key: keyof Meine,
        label: string,
        hint?: string,
        lang = false,
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`${pfad}-${key}`}>{label}</Label>
            {lang ? (
                <Textarea
                    id={`${pfad}-${key}`}
                    rows={4}
                    value={form.data[key]}
                    onChange={(e) => form.setData(key, e.target.value)}
                />
            ) : (
                <Input
                    id={`${pfad}-${key}`}
                    value={form.data[key]}
                    onChange={(e) => form.setData(key, e.target.value)}
                />
            )}
            {hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <details
            className="mt-10 rounded-lg border border-border p-4"
            open={gesetzt}
        >
            <summary className="cursor-pointer font-semibold">{titel}</summary>
            <p className="mt-2 text-sm text-muted-foreground">{hinweis}</p>
            <form onSubmit={submit} className="mt-4 grid gap-4 sm:grid-cols-2">
                {feld('abbreviation_override', 'Abkürzung')}
                {feld('name_override', 'Bezeichnung')}
                <div className="sm:col-span-2">
                    {feld(
                        'short_description_override',
                        'Kurzbeschreibung',
                        `Höchstens 160 Zeichen (${form.data.short_description_override.length}/160)`,
                    )}
                </div>
                <div className="sm:col-span-2">
                    {feld(
                        'description_override',
                        'Beschreibung',
                        undefined,
                        true,
                    )}
                </div>
                <div className="sm:col-span-2">
                    {feld(
                        'aliases_override',
                        'Eigene Schreibweisen',
                        'Mehrere mit «;» trennen; auch in der Suche',
                    )}
                </div>
                <div className="flex flex-wrap gap-2 sm:col-span-2">
                    <Button type="submit" disabled={form.processing}>
                        Speichern
                    </Button>
                    {gesetzt ? (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() =>
                                router.delete(pfad, { preserveScroll: true })
                            }
                        >
                            Entfernen
                        </Button>
                    ) : null}
                </div>
            </form>
        </details>
    );
}
