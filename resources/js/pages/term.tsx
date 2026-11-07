import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ListBadge, StatusBadge } from '@/components/tags';

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
    // nur angemeldet (DEC-020): persönliche Darstellung des eigenen Kontos
    persoenlich?: boolean;
    gemeinsam?: {
        abkuerzung: string;
        bezeichnung: string;
        kurz: string | null;
        beschreibung: string | null;
    };
    meine?: Meine;
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
                        <ListBadge key={k} list="kategorien" label={k} />
                    ))}
                    {b.status !== 'aktuell' ? (
                        <StatusBadge status={b.status} />
                    ) : null}
                </div>

                {b.persoenlich && b.gemeinsam ? (
                    <p className="mt-3 text-sm text-muted-foreground">
                        <Badge variant="secondary">Meine Darstellung</Badge>{' '}
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

                {b.meine ? (
                    <MeineDarstellung
                        slug={b.slug}
                        werte={b.meine}
                        gesetzt={b.persoenlich === true}
                    />
                ) : null}

                <p className="mt-10 text-xs text-muted-foreground">
                    Erklärungen ohne amtliche Verbindlichkeit; massgebend sind
                    die genannten Quellen.
                </p>
            </article>
        </>
    );
}

/** «Meine Darstellung» (DEC-020): nur für das eigene Konto sichtbar, keine Moderation, nie öffentlich. */
function MeineDarstellung({
    slug,
    werte,
    gesetzt,
}: {
    slug: string;
    werte: Meine;
    gesetzt: boolean;
}) {
    const form = useForm<Meine>(werte);
    const { flash } = usePage().props;
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/begriffe/${slug}/meine-darstellung`, {
            preserveScroll: true,
        });
    };
    const feld = (
        key: keyof Meine,
        label: string,
        hint?: string,
        lang = false,
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={key}>{label}</Label>
            {lang ? (
                <Textarea
                    id={key}
                    rows={4}
                    value={form.data[key]}
                    onChange={(e) => form.setData(key, e.target.value)}
                />
            ) : (
                <Input
                    id={key}
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
            <summary className="cursor-pointer font-semibold">
                Meine Darstellung
            </summary>
            {flash.status ? (
                <p className="mt-2 text-sm font-medium" role="status">
                    {flash.status}
                </p>
            ) : null}
            <p className="mt-2 text-sm text-muted-foreground">
                Nur für dich sichtbar, wenn du angemeldet bist. Leere Felder
                zeigen die gemeinsame Form. Wird nicht veröffentlicht und nicht
                über die API weitergegeben; mit deinem Konto gelöscht.
            </p>
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
                                router.delete(
                                    `/begriffe/${slug}/meine-darstellung`,
                                    { preserveScroll: true },
                                )
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
