import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { confirmAction } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ListBadge, ListBadges, StatusBadge } from '@/components/tags';
import { zurueckOderStart } from '@/lib/navigation';

type Meine = {
    abbreviation_override: string;
    name_override: string;
    short_description_override: string;
    description_override: string;
    aliases_override: string;
};

type Begriff = {
    slug: string;
    code: string;
    abkuerzung: string;
    bezeichnung: string;
    abkuerzung_offiziell: string | null;
    bezeichnung_offiziell: string | null;
    aliase: string[];
    aliase_offiziell: string[];
    alternative: string[];
    suchbegriffe: string[];
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
    const herkunftLabels = b.herkunft.map((h) => h.label);
    // Schreibweisen aus Meiner Darstellung bzw. der Organisation
    const eigene = b.aliase.filter(
        (a) => !b.aliase_offiziell.includes(a) && !b.alternative.includes(a),
    );

    return (
        <>
            <Head title={titel} />
            <div className="flex items-center justify-between gap-2 text-sm">
                <Link
                    href="/"
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← Alle Begriffe
                </Link>
                {/* Schliessen (#250): zurück zur vorherigen Seite der Website, sonst zur Startseite */}
                <Button
                    variant="outline"
                    size="sm"
                    onClick={zurueckOderStart}
                    aria-label="Detailansicht schliessen"
                >
                    <X aria-hidden /> Schliessen
                </Button>
            </div>

            <article className="mt-4">
                <h1 className="text-3xl font-semibold tracking-tight">
                    {b.abkuerzung ? (
                        <span translate="no">{b.abkuerzung}</span>
                    ) : null}
                    {b.abkuerzung && b.bezeichnung ? (
                        <span className="text-muted-foreground"> – </span>
                    ) : null}
                    <span translate="no">{b.bezeichnung}</span>
                </h1>

                <div className="mt-3 flex flex-wrap items-start gap-2">
                    <ListBadges list="herkunft" labels={herkunftLabels} />
                    <ListBadges list="kategorien" labels={b.kategorien} />
                    <StatusBadge status={b.status} />
                    {b.mehrdeutig ? (
                        <Badge variant="outline">mehrdeutig</Badge>
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

                {b.kurz ? (
                    <p className="mt-6 max-w-prose text-lg">{b.kurz}</p>
                ) : null}
                {b.beschreibung ? (
                    <p className="mt-4 max-w-prose whitespace-pre-line">
                        {b.beschreibung}
                    </p>
                ) : null}

                {/* alle öffentlichen Felder (#163); leere als «–» */}
                <dl className="mt-8 grid gap-x-6 gap-y-3 border-t border-border pt-6 text-sm sm:grid-cols-[14rem_1fr]">
                    <Feld titel="Abkürzung (offiziell)">
                        {b.abkuerzung_offiziell ? (
                            <span translate="no">{b.abkuerzung_offiziell}</span>
                        ) : null}
                    </Feld>
                    <Feld titel="Bezeichnung (offiziell)">
                        {b.bezeichnung_offiziell ? (
                            <span translate="no">
                                {b.bezeichnung_offiziell}
                            </span>
                        ) : null}
                    </Feld>
                    <Feld titel="Aliase (offiziell)">
                        <Liste werte={b.aliase_offiziell} />
                    </Feld>
                    <Feld titel="Alternative Schreibweisen">
                        <Liste werte={b.alternative} />
                    </Feld>
                    {eigene.length > 0 ? (
                        <Feld titel="Eigene Schreibweisen">
                            <Liste werte={eigene} />
                        </Feld>
                    ) : null}
                    <Feld titel="Suchbegriffe">
                        <Liste werte={b.suchbegriffe} />
                    </Feld>
                    <Feld titel="Kurzbeschreibung">{b.kurz}</Feld>
                    <Feld titel="Herkunft">
                        {b.herkunft.length > 0 ? (
                            <span className="flex flex-col items-start gap-1">
                                {b.herkunft.map((h) => (
                                    <span
                                        key={h.label}
                                        className="flex flex-wrap items-center gap-2"
                                    >
                                        <ListBadge
                                            list="herkunft"
                                            label={h.label}
                                        />
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
                            </span>
                        ) : null}
                    </Feld>
                    <Feld titel="Kategorien">
                        {b.kategorien.length > 0 ? (
                            <ListBadges
                                list="kategorien"
                                labels={b.kategorien}
                            />
                        ) : null}
                    </Feld>
                    <Feld titel="Status">
                        <StatusBadge status={b.status} />
                    </Feld>
                    <Feld titel="URL">
                        {b.url ? (
                            <a
                                href={b.url}
                                className="break-all text-link underline underline-offset-4"
                                rel="noopener noreferrer"
                            >
                                {b.url}
                            </a>
                        ) : null}
                    </Feld>
                    <Feld titel="Gültig ab">{b.gueltig_ab}</Feld>
                    <Feld titel="Gültig bis">{b.gueltig_bis}</Feld>
                    <Feld titel="Revision">V{b.revision}</Feld>
                    <Feld titel="Veröffentlicht">{b.veroeffentlicht}</Feld>
                    <Feld titel="Permalink">
                        <span className="flex flex-wrap items-center gap-2">
                            <a
                                href={b.permalink}
                                className="text-link underline underline-offset-4"
                            >
                                {b.permalink.replace(/^https?:\/\//, '')}
                            </a>
                            <KopierenKnopf text={b.permalink} />
                        </span>
                    </Feld>
                    <Feld titel="Code">
                        <code>{b.code}</code>
                    </Feld>
                    {b.verlauf.length > 1 ? (
                        <Feld titel="Verlauf">
                            <ol className="flex flex-wrap gap-x-3 gap-y-1">
                                {b.verlauf.map((v) => (
                                    <li key={v.revision}>
                                        V{v.revision}
                                        {v.am ? ` (${v.am})` : ''}
                                    </li>
                                ))}
                            </ol>
                        </Feld>
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

/** Zeile der Feldliste; ohne Wert «–». */
function Feld({ titel, children }: { titel: string; children: ReactNode }) {
    const leer =
        children === null ||
        children === undefined ||
        children === '' ||
        children === false;

    return (
        <>
            <dt className="text-muted-foreground">{titel}</dt>
            <dd>
                {leer ? (
                    <span className="text-muted-foreground">–</span>
                ) : (
                    children
                )}
            </dd>
        </>
    );
}

function Liste({ werte }: { werte: string[] }) {
    return werte.length > 0 ? (
        <span translate="no">{werte.join('; ')}</span>
    ) : (
        <span className="text-muted-foreground">–</span>
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
    const [loeschen, setLoeschen] = useState(true);
    const uebernehmen = async () => {
        const ok = await confirmAction(
            'Meine Darstellung als Darstellung der Organisation übernehmen? Sie ersetzt die bisherige Darstellung der Organisation für diesen Begriff und ist danach für alle Mitglieder sichtbar.' +
                (loeschen ? ' Meine Darstellung wird danach gelöscht.' : ''),
            { confirmLabel: 'Übernehmen', destructive: true },
        );
        if (ok) {
            router.post(
                `/begriffe/${slug}/organisation-darstellung/aus-meiner`,
                { loeschen },
                { preserveScroll: true },
            );
        }
    };

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
            hinweis={`Nur für dich sichtbar, wenn du angemeldet bist – auch deine Organisation sieht sie nicht. ${freigabe} Leere Felder zeigen die Form deiner Organisation bzw. die gemeinsame. Wird nicht veröffentlicht und nicht über die API weitergegeben; mit deinem Konto gelöscht.`}
        >
            {admin && gesetzt ? (
                <div className="mt-4 rounded-md border border-border bg-muted/40 p-3 text-sm">
                    <p className="font-medium">
                        Als Darstellung der Organisation übernehmen
                    </p>
                    <p className="mt-1 text-muted-foreground">
                        Überträgt deine gespeicherten Werte in die Darstellung
                        der Organisation (ersetzt die bisherige, sichtbar für
                        alle Mitglieder).
                    </p>
                    <label className="mt-2 flex items-center gap-2">
                        <input
                            type="checkbox"
                            checked={loeschen}
                            onChange={(e) => setLoeschen(e.target.checked)}
                        />
                        Meine Darstellung danach löschen
                    </label>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="mt-2"
                        onClick={uebernehmen}
                    >
                        Übernehmen
                    </Button>
                </div>
            ) : null}
        </Darstellung>
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
    children,
}: {
    titel: string;
    pfad: string;
    werte: Meine;
    gesetzt: boolean;
    hinweis: string;
    children?: ReactNode;
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
            {children}
        </details>
    );
}
