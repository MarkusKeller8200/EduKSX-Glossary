import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

type Werte = {
    official_abbreviation: string;
    official_aliases: string;
    alternative_aliases: string;
    official_name: string;
    short_description: string;
    description: string;
    url: string;
    note: string;
    categories: string[];
    sources: string[];
    lifecycle_status: string;
    search_terms: string;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
    translations: Record<Sprache, Uebersetzung>;
};

type Sprache = 'fr' | 'it' | 'en';
type Uebersetzung = {
    abbreviation: string;
    name: string;
    short_description: string;
    description: string;
};

/** Übersetzungssprachen (#282); Deutsch steht in den Feldern oben. */
const SPRACHEN: [Sprache, string][] = [
    ['fr', 'Französisch'],
    ['it', 'Italienisch'],
    ['en', 'Englisch'],
];

type Option = { key: string; label: string };

/** Formular der Redaktion (#14): anlegen und ändern. Gespeichert wird immer als Entwurf (neue Revision). */
export default function TermForm({
    begriff,
    werte,
    listen,
    offiziellErlaubt,
}: {
    begriff: { id: string; titel: string } | null;
    werte: Werte;
    listen: {
        kategorien: Option[];
        herkunft: Option[];
        status: Record<string, string>;
    };
    offiziellErlaubt: boolean;
}) {
    const form = useForm<Werte & { form?: string }>(werte);
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (begriff) {
            form.put(`/admin/begriffe/${begriff.id}`);
        } else {
            form.post('/admin/begriffe');
        }
    };
    const toggle = (
        field: 'categories' | 'sources',
        key: string,
        on: boolean,
    ) =>
        form.setData(
            field,
            on
                ? [...form.data[field], key]
                : form.data[field].filter((k) => k !== key),
        );
    const text = (
        field: Exclude<keyof Werte, 'translations'>,
        label: string,
        hint?: string,
        props: { disabled?: boolean; maxLength?: number; type?: string } = {},
    ) => (
        <Feld id={field} label={label} hint={hint} error={errors[field]}>
            <Input
                id={field}
                value={String(form.data[field] ?? '')}
                onChange={(e) => form.setData(field, e.target.value as never)}
                aria-invalid={errors[field] ? true : undefined}
                {...props}
            />
        </Feld>
    );
    const gesperrt = !offiziellErlaubt;
    const setUebersetzung = (
        sprache: Sprache,
        feld: keyof Uebersetzung,
        wert: string,
    ) =>
        form.setData('translations', {
            ...form.data.translations,
            [sprache]: { ...form.data.translations[sprache], [feld]: wert },
        });

    return (
        <>
            <Head
                title={
                    begriff ? `Bearbeiten: ${begriff.titel}` : 'Neuer Begriff'
                }
            />
            <p className="text-sm">
                <Link
                    href={
                        begriff
                            ? `/admin/begriffe/${begriff.id}`
                            : '/admin/begriffe'
                    }
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← Zurück
                </Link>
            </p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                {begriff ? `Bearbeiten: ${begriff.titel}` : 'Neuer Begriff'}
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
                Gespeichert wird als Entwurf (neue Revision). Öffentlich wird
                die Änderung erst mit «Veröffentlichen».
            </p>

            {errors.form ? (
                <Alert className="mt-4" variant="destructive">
                    <AlertDescription>{errors.form}</AlertDescription>
                </Alert>
            ) : null}

            <form onSubmit={submit} className="mt-6 grid max-w-4xl gap-6">
                <fieldset className="grid gap-4 sm:grid-cols-2">
                    <legend className="mb-2 font-semibold">
                        Offiziell
                        {gesperrt
                            ? ' (nur mit Recht «offizielle Begriffe pflegen»)'
                            : ''}
                    </legend>
                    {text(
                        'official_abbreviation',
                        'Abkürzung offiziell',
                        undefined,
                        { disabled: gesperrt },
                    )}
                    {text('official_name', 'Bezeichnung offiziell', undefined, {
                        disabled: gesperrt,
                    })}
                    {text(
                        'official_aliases',
                        'Aliase offiziell',
                        'Mehrere mit «;» trennen',
                        { disabled: gesperrt },
                    )}
                </fieldset>

                <fieldset className="grid gap-4">
                    <legend className="mb-2 font-semibold">
                        Alternative Schreibweisen
                    </legend>
                    {text(
                        'alternative_aliases',
                        'Alternative Schreibweisen',
                        'Gemeinsam gültig und öffentlich; mehrere mit «;» trennen. Persönliche Formen unter «Meine Darstellung» auf der Begriffsseite.',
                    )}
                </fieldset>

                <fieldset className="grid gap-4">
                    <legend className="mb-2 font-semibold">Erklärung</legend>
                    <Feld
                        id="short_description"
                        label="Kurzbeschreibung"
                        hint={`Ein Satz, höchstens 160 Zeichen (${form.data.short_description.length}/160)`}
                        error={errors.short_description}
                    >
                        <Input
                            id="short_description"
                            maxLength={160}
                            value={form.data.short_description}
                            onChange={(e) =>
                                form.setData(
                                    'short_description',
                                    e.target.value,
                                )
                            }
                        />
                    </Feld>
                    <Feld
                        id="description"
                        label="Beschreibung"
                        error={errors.description}
                    >
                        <Textarea
                            id="description"
                            rows={6}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                    </Feld>
                    {text('url', 'URL (Quelle)', 'Mit http:// oder https://', {
                        type: 'url',
                    })}
                    <Feld
                        id="note"
                        label="Notiz (intern, nicht öffentlich)"
                        error={errors.note}
                    >
                        <Textarea
                            id="note"
                            rows={3}
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                        />
                    </Feld>
                </fieldset>

                <Uebersetzungen
                    werte={form.data.translations}
                    fehler={errors}
                    onChange={setUebersetzung}
                />

                <fieldset className="grid gap-4 sm:grid-cols-2">
                    <legend className="mb-2 font-semibold">Einordnung</legend>
                    <Auswahl
                        titel="Kategorien"
                        optionen={listen.kategorien}
                        gewaehlt={form.data.categories}
                        onToggle={(k, on) => toggle('categories', k, on)}
                        error={errors.categories}
                    />
                    <Auswahl
                        titel="Herkunft"
                        optionen={listen.herkunft}
                        gewaehlt={form.data.sources}
                        onToggle={(k, on) => toggle('sources', k, on)}
                        error={errors.sources}
                    />
                    <Feld
                        id="lifecycle_status"
                        label="Status"
                        error={errors.lifecycle_status}
                    >
                        <NativeSelect
                            id="lifecycle_status"
                            value={form.data.lifecycle_status}
                            onChange={(e) =>
                                form.setData('lifecycle_status', e.target.value)
                            }
                        >
                            {Object.entries(listen.status).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </NativeSelect>
                    </Feld>
                    {text(
                        'search_terms',
                        'Suchbegriffe',
                        'Mit «;» oder «,» trennen',
                    )}
                    {text('valid_from', 'Gültig ab', undefined, {
                        type: 'date',
                    })}
                    {text('valid_until', 'Gültig bis', undefined, {
                        type: 'date',
                    })}
                    <div className="flex items-center gap-2 self-end pb-2">
                        <Checkbox
                            id="is_active"
                            checked={form.data.is_active}
                            onCheckedChange={(v) =>
                                form.setData('is_active', v === true)
                            }
                        />
                        <Label htmlFor="is_active">
                            Aktiv (auf Website und API anzeigen)
                        </Label>
                    </div>
                </fieldset>

                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Speichern (Entwurf)
                    </Button>
                    <Button asChild variant="outline">
                        <Link
                            href={
                                begriff
                                    ? `/admin/begriffe/${begriff.id}`
                                    : '/admin/begriffe'
                            }
                        >
                            Abbrechen
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

function Feld({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}

function Auswahl({
    titel,
    optionen,
    gewaehlt,
    onToggle,
    error,
}: {
    titel: string;
    optionen: Option[];
    gewaehlt: string[];
    onToggle: (key: string, on: boolean) => void;
    error?: string;
}) {
    return (
        <div className="space-y-1.5">
            <p className="text-sm font-medium">{titel}</p>
            {optionen.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    Noch keine Einträge –{' '}
                    <Link
                        href="/admin/listen"
                        className="text-link underline underline-offset-4"
                    >
                        Listen pflegen
                    </Link>
                </p>
            ) : (
                <div className="max-h-48 space-y-1 overflow-y-auto rounded-md border border-border p-2">
                    {optionen.map((o) => (
                        <div key={o.key} className="flex items-center gap-2">
                            <Checkbox
                                id={`${titel}-${o.key}`}
                                checked={gewaehlt.includes(o.key)}
                                onCheckedChange={(v) =>
                                    onToggle(o.key, v === true)
                                }
                            />
                            <Label
                                htmlFor={`${titel}-${o.key}`}
                                className="font-normal"
                            >
                                {o.label}
                            </Label>
                        </div>
                    ))}
                </div>
            )}
            <InputError message={error} />
        </div>
    );
}

/**
 * Übersetzungen (#282): je Sprache Abkürzung, Bezeichnung und Texte. Leere Felder liefert die API auf Deutsch.
 * Gespeichert wird mit dem Begriff (Revision), öffentlich erst mit «Veröffentlichen».
 */
function Uebersetzungen({
    werte,
    fehler,
    onChange,
}: {
    werte: Record<Sprache, Uebersetzung>;
    fehler: Record<string, string | undefined>;
    onChange: (
        sprache: Sprache,
        feld: keyof Uebersetzung,
        wert: string,
    ) => void;
}) {
    const [aktiv, setAktiv] = useState<Sprache>('fr');
    const w = werte[aktiv];
    const id = (feld: string) => `translations-${aktiv}-${feld}`;
    const err = (feld: string) => fehler[`translations.${aktiv}.${feld}`];
    const gefuellt = (s: Sprache) =>
        Object.values(werte[s]).some((v) => v.trim() !== '');

    return (
        <fieldset className="grid gap-4">
            <legend className="mb-2 font-semibold">Übersetzungen</legend>
            <p className="-mt-2 text-xs text-muted-foreground">
                Optional. Leere Felder liefert die API auf Deutsch.
            </p>
            <div
                role="tablist"
                aria-label="Sprache der Übersetzung"
                className="flex flex-wrap gap-1"
            >
                {SPRACHEN.map(([s, label]) => (
                    <Button
                        key={s}
                        type="button"
                        role="tab"
                        aria-selected={aktiv === s}
                        variant={aktiv === s ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setAktiv(s)}
                    >
                        {label}
                        {gefuellt(s) ? ' •' : ''}
                    </Button>
                ))}
            </div>
            <div role="tabpanel" className="grid gap-4 sm:grid-cols-2">
                <Feld
                    id={id('abbreviation')}
                    label="Abkürzung"
                    error={err('abbreviation')}
                >
                    <Input
                        id={id('abbreviation')}
                        lang={aktiv}
                        maxLength={60}
                        value={w.abbreviation}
                        onChange={(e) =>
                            onChange(aktiv, 'abbreviation', e.target.value)
                        }
                    />
                </Feld>
                <Feld id={id('name')} label="Bezeichnung" error={err('name')}>
                    <Input
                        id={id('name')}
                        lang={aktiv}
                        value={w.name}
                        onChange={(e) =>
                            onChange(aktiv, 'name', e.target.value)
                        }
                    />
                </Feld>
                <div className="sm:col-span-2">
                    <Feld
                        id={id('short_description')}
                        label="Kurzbeschreibung"
                        hint={`Höchstens 160 Zeichen (${w.short_description.length}/160)`}
                        error={err('short_description')}
                    >
                        <Input
                            id={id('short_description')}
                            lang={aktiv}
                            maxLength={160}
                            value={w.short_description}
                            onChange={(e) =>
                                onChange(
                                    aktiv,
                                    'short_description',
                                    e.target.value,
                                )
                            }
                        />
                    </Feld>
                </div>
                <div className="sm:col-span-2">
                    <Feld
                        id={id('description')}
                        label="Beschreibung"
                        error={err('description')}
                    >
                        <Textarea
                            id={id('description')}
                            lang={aktiv}
                            rows={4}
                            value={w.description}
                            onChange={(e) =>
                                onChange(aktiv, 'description', e.target.value)
                            }
                        />
                    </Feld>
                </div>
            </div>
        </fieldset>
    );
}
