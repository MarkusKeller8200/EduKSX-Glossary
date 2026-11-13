import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { isColorName, palette } from '@/lib/colors';
import { cn } from '@/lib/utils';
import DataGrid from '@/components/data-grid';
import IconAction from '@/components/icon-action';
import { Pencil, Trash2 } from 'lucide-react';
import { confirmAction } from '@/components/confirm-dialog';

type Eintrag = {
    id: string;
    key: string;
    abbreviation: string | null;
    name: string;
    label: string | null;
    description: string | null;
    url: string | null;
    note: string | null;
    color: string | null;
    sort_order: number;
    begriffe: number;
    translations: Record<Sprache, ListUebersetzung>;
    lock_version: number;
};

type Sprache = 'fr' | 'it' | 'en';
type ListUebersetzung = { name: string; label: string; description: string };

/** Übersetzungssprachen (#282). */
const SPRACHEN: [Sprache, string][] = [
    ['fr', 'Französisch'],
    ['it', 'Italienisch'],
    ['en', 'Englisch'],
];

const LEER: ListUebersetzung = { name: '', label: '', description: '' };

type Werte = {
    key: string;
    abbreviation: string;
    name: string;
    label: string;
    description: string;
    url: string;
    color: string;
    sort_order: string;
    translations: Record<Sprache, ListUebersetzung>;
    lock_version: number;
};

const TITEL = { kategorien: 'Kategorien', herkunft: 'Herkunft' } as const;

/** Anzeigenamen der Palette (#77). */
const FARBNAMEN: Record<string, string> = {
    'ci-violet': 'Violett (CI)',
    'ci-orange': 'Orange (CI)',
    'ci-blue': 'Blau (CI)',
    brand: 'Blau (Links)',
    slate: 'Grau',
    sky: 'Himmelblau',
    cyan: 'Cyan',
    teal: 'Petrol',
    emerald: 'Grün',
    amber: 'Bernstein',
    orange: 'Orange',
    rose: 'Rosa',
    violet: 'Violett',
    indigo: 'Indigo',
};
type Liste = keyof typeof TITEL;

/** Listen pflegen (#14): Kategorien und Herkunft wie im Intranet. Schlüssel bleiben stabil (API, Excel). */
export default function Lists({
    listen,
    farbwahl: farben,
}: {
    listen: Record<Liste, Eintrag[]>;
    farbwahl: string[];
}) {
    return (
        <>
            <Head title="Listen" />
            <h1 className="text-3xl font-semibold tracking-tight">Listen</h1>
            <p className="mt-2 text-sm text-muted-foreground">
                Kategorien und Herkunft der Begriffe. Der Schlüssel bleibt nach
                dem Anlegen fest (API, Excel-Import); gelöscht werden kann nur,
                was kein Begriff verwendet.
            </p>
            {(Object.keys(TITEL) as Liste[]).map((liste) => (
                <Tabelle
                    key={liste}
                    liste={liste}
                    eintraege={listen[liste]}
                    farben={farben}
                />
            ))}
        </>
    );
}

function Tabelle({
    liste,
    eintraege,
    farben,
}: {
    liste: Liste;
    eintraege: Eintrag[];
    farben: string[];
}) {
    const [bearbeiten, setBearbeiten] = useState<string | null>(null);
    const bearbeitet = eintraege.find((e) => e.id === bearbeiten) ?? null;

    return (
        <section className="mt-8">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <h2 className="text-xl font-semibold">{TITEL[liste]}</h2>
                <ExcelListe liste={liste} />
            </div>
            <div className="mt-3">
                <DataGrid
                    id={`admin.lists.${liste}`}
                    caption={TITEL[liste]}
                    rows={eintraege}
                    getRowId={(e: Eintrag) => e.id}
                    empty="Noch keine Einträge."
                    columns={[
                        {
                            id: 'kuerzel',
                            header: 'Kürzel',
                            width: 110,
                            sticky: 'left',
                            className: 'font-medium',
                            noTranslate: true,
                            sortValue: (e) => e.abbreviation,
                            cell: (e) => e.abbreviation,
                        },
                        {
                            id: 'bezeichnung',
                            header: 'Bezeichnung',
                            width: 240,
                            sortValue: (e) => e.name,
                            cell: (e) => e.name,
                        },
                        {
                            id: 'schluessel',
                            header: 'Schlüssel',
                            width: 130,
                            className: 'font-mono text-xs',
                            sortValue: (e) => e.key,
                            cell: (e) => e.key,
                        },
                        {
                            id: 'label',
                            header: 'Label',
                            width: 130,
                            sortValue: (e) => e.label,
                            cell: (e) => e.label,
                        },
                        {
                            id: 'farbe',
                            header: 'Farbe',
                            width: 150,
                            sortValue: (e) => e.color,
                            cell: (e) => (
                                <>
                                    <Badge
                                        variant="outline"
                                        className={
                                            palette[
                                                isColorName(e.color)
                                                    ? e.color
                                                    : 'slate'
                                            ].badge
                                        }
                                    >
                                        {e.label || e.abbreviation || e.name}
                                    </Badge>
                                    <span className="sr-only">
                                        {' '}
                                        Farbe {e.color ?? 'keine'}
                                    </span>
                                </>
                            ),
                        },
                        {
                            id: 'url',
                            header: 'URL',
                            width: 200,
                            className: 'break-all',
                            sortValue: (e) => e.url,
                            cell: (e) => e.url,
                        },
                        {
                            id: 'reihenfolge',
                            header: 'Reihenfolge',
                            width: 110,
                            className: 'tabular-nums',
                            sortValue: (e) => e.sort_order,
                            cell: (e) => e.sort_order,
                        },
                        {
                            id: 'begriffe',
                            header: 'Begriffe',
                            width: 100,
                            className: 'tabular-nums',
                            sortValue: (e) => e.begriffe,
                            cell: (e) => e.begriffe,
                        },
                        {
                            id: 'aktionen',
                            label: 'Aktionen',
                            header: <span className="sr-only">Aktionen</span>,
                            width: 96,
                            fixed: true,
                            sticky: 'right',
                            className: 'py-1 whitespace-nowrap',
                            cell: (e) => (
                                <div className="flex">
                                    <IconAction
                                        icon={Pencil}
                                        label={`«${e.name}» bearbeiten`}
                                        onClick={() => setBearbeiten(e.id)}
                                    />
                                    <IconAction
                                        icon={Trash2}
                                        destructive
                                        label={
                                            e.begriffe > 0
                                                ? `«${e.name}» wird noch verwendet – nicht löschbar`
                                                : `«${e.name}» löschen`
                                        }
                                        disabled={e.begriffe > 0}
                                        onClick={async () => {
                                            if (
                                                await confirmAction(
                                                    `«${e.name}» löschen?`,
                                                )
                                            ) {
                                                router.delete(
                                                    `/admin/listen/${liste}/${e.id}`,
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                    />
                                </div>
                            ),
                        },
                    ]}
                />
            </div>
            {bearbeitet ? (
                <div className="mt-3 rounded-lg border border-border p-4">
                    <h3 className="mb-3 font-semibold">
                        «{bearbeitet.name}» bearbeiten
                    </h3>
                    <Formular
                        key={bearbeitet.id}
                        liste={liste}
                        farben={farben}
                        eintrag={bearbeitet}
                        onFertig={() => setBearbeiten(null)}
                    />
                </div>
            ) : null}
            <details className="mt-3">
                <summary className="cursor-pointer text-sm text-link">
                    Neuer Eintrag in «{TITEL[liste]}»
                </summary>
                <div className="mt-3">
                    <Formular
                        liste={liste}
                        farben={farben}
                        eintrag={null}
                        onFertig={() => {}}
                    />
                </div>
            </details>
        </section>
    );
}

function Formular({
    liste,
    farben,
    eintrag,
    onFertig,
}: {
    liste: Liste;
    farben: string[];
    eintrag: Eintrag | null;
    onFertig: () => void;
}) {
    const form = useForm<Werte>({
        key: eintrag?.key ?? '',
        abbreviation: eintrag?.abbreviation ?? '',
        name: eintrag?.name ?? '',
        label: eintrag?.label ?? '',
        description: eintrag?.description ?? '',
        url: eintrag?.url ?? '',
        color: eintrag?.color ?? '',
        sort_order: String(eintrag?.sort_order ?? ''),
        translations: eintrag?.translations ?? {
            fr: LEER,
            it: LEER,
            en: LEER,
        },
        lock_version: eintrag?.lock_version ?? 0,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const setUebersetzung = (
        sprache: Sprache,
        feld: keyof ListUebersetzung,
        wert: string,
    ) =>
        form.setData('translations', {
            ...form.data.translations,
            [sprache]: { ...form.data.translations[sprache], [feld]: wert },
        });
    const submit = (ev: FormEvent) => {
        ev.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!eintrag) {
                    form.reset();
                }
                onFertig();
            },
        };
        if (eintrag) {
            form.put(`/admin/listen/${liste}/${eintrag.id}`, options);
        } else {
            form.post(`/admin/listen/${liste}`, options);
        }
    };
    const feld = (
        name: Exclude<keyof Werte, 'translations' | 'lock_version'>,
        label: string,
        disabled = false,
    ) => (
        <div className="space-y-1">
            <Label htmlFor={`${liste}-${eintrag?.id ?? 'neu'}-${name}`}>
                {label}
            </Label>
            <Input
                id={`${liste}-${eintrag?.id ?? 'neu'}-${name}`}
                value={form.data[name]}
                disabled={disabled}
                onChange={(e) => form.setData(name, e.target.value)}
            />
            <InputError message={form.errors[name]} />
        </div>
    );

    return (
        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-3">
            {feld('key', 'Schlüssel (leer = automatisch)', eintrag !== null)}
            {feld('abbreviation', 'Kürzel')}
            {feld('name', 'Bezeichnung')}
            {feld('label', 'Label (kurz)')}
            {feld('url', 'URL')}
            {feld('sort_order', 'Reihenfolge')}
            <fieldset className="space-y-1 sm:col-span-3">
                <legend className="text-sm font-medium">Farbe</legend>
                {/* Farben direkt als Labels in ihrer Farbe (#107) – Radio-Gruppe, mit Tastatur bedienbar */}
                <div className="flex flex-wrap gap-2">
                    {['', ...farben].map((f) => {
                        const id = `${liste}-${eintrag?.id ?? 'neu'}-color-${f || 'keine'}`;
                        const gewaehlt = form.data.color === f;

                        return (
                            <label
                                key={f || 'keine'}
                                htmlFor={id}
                                className={cn(
                                    'cursor-pointer rounded-md ring-offset-2 ring-offset-background has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50',
                                    gewaehlt && 'ring-2 ring-foreground',
                                )}
                            >
                                <input
                                    id={id}
                                    type="radio"
                                    name={`${liste}-${eintrag?.id ?? 'neu'}-color`}
                                    value={f}
                                    checked={gewaehlt}
                                    onChange={() => form.setData('color', f)}
                                    className="sr-only"
                                />
                                <Badge
                                    variant="outline"
                                    className={
                                        palette[isColorName(f) ? f : 'slate']
                                            .badge
                                    }
                                >
                                    {f === ''
                                        ? 'keine (grau)'
                                        : (FARBNAMEN[f] ?? f)}
                                </Badge>
                            </label>
                        );
                    })}
                </div>
                <InputError message={form.errors.color} />
            </fieldset>
            <div className="space-y-1 sm:col-span-2">
                <Label htmlFor={`${liste}-${eintrag?.id ?? 'neu'}-description`}>
                    Beschreibung (öffentlich)
                </Label>
                <Input
                    id={`${liste}-${eintrag?.id ?? 'neu'}-description`}
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                />
                <InputError message={form.errors.description} />
            </div>
            <details className="sm:col-span-3">
                <summary className="cursor-pointer text-sm font-medium">
                    Übersetzungen (optional; leer = deutsch)
                </summary>
                <div className="mt-2 grid gap-3">
                    {SPRACHEN.map(([s, sprachname]) => (
                        <fieldset
                            key={s}
                            lang={s}
                            className="grid gap-3 sm:grid-cols-3"
                        >
                            <legend className="mb-1 text-sm text-muted-foreground">
                                {sprachname}
                            </legend>
                            {(
                                [
                                    ['name', 'Bezeichnung'],
                                    ['label', 'Label (kurz)'],
                                    ['description', 'Beschreibung'],
                                ] as [keyof ListUebersetzung, string][]
                            ).map(([f, label]) => {
                                const id = `${liste}-${eintrag?.id ?? 'neu'}-${s}-${f}`;
                                return (
                                    <div key={f} className="space-y-1">
                                        <Label htmlFor={id}>{label}</Label>
                                        <Input
                                            id={id}
                                            value={form.data.translations[s][f]}
                                            onChange={(e) =>
                                                setUebersetzung(
                                                    s,
                                                    f,
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[`translations.${s}.${f}`]
                                            }
                                        />
                                    </div>
                                );
                            })}
                        </fieldset>
                    ))}
                </div>
            </details>
            {errors.form ? (
                <InputError className="sm:col-span-3" message={errors.form} />
            ) : null}
            <div className="flex gap-2 sm:col-span-3">
                <Button type="submit" size="sm" disabled={form.processing}>
                    Speichern
                </Button>
                {eintrag ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={onFertig}
                    >
                        Abbrechen
                    </Button>
                ) : null}
            </div>
        </form>
    );
}

/** Excel-Export und -Import der Liste (#101); der Import zeigt zuerst eine Vorschau. */
function ExcelListe({ liste }: { liste: Liste }) {
    const form = useForm<{ datei: File | null }>({ datei: null });
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/admin/listen/${liste}/import`, { forceFormData: true });
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Button asChild variant="outline" size="sm">
                <a href={`/admin/listen/${liste}/export`}>Excel-Export</a>
            </Button>
            <form onSubmit={submit} className="flex items-center gap-2">
                <Label htmlFor={`import-${liste}`} className="sr-only">
                    Datei für den Import ({TITEL[liste]})
                </Label>
                <Input
                    id={`import-${liste}`}
                    type="file"
                    accept=".xlsx,.csv"
                    className="h-8 max-w-56 text-xs"
                    onChange={(e) =>
                        form.setData('datei', e.target.files?.[0] ?? null)
                    }
                />
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    disabled={!form.data.datei || form.processing}
                >
                    Import prüfen
                </Button>
            </form>
            <InputError message={errors.datei} />
        </div>
    );
}
