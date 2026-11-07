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
};

type Werte = {
    key: string;
    abbreviation: string;
    name: string;
    label: string;
    description: string;
    url: string;
    color: string;
    sort_order: string;
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

    return (
        <section className="mt-8">
            <h2 className="text-xl font-semibold">{TITEL[liste]}</h2>
            <div className="mt-3 overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted text-left">
                        <tr>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Schlüssel
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Kürzel
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Bezeichnung
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Label
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Farbe
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                URL
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Begriffe
                            </th>
                            <th scope="col" className="px-3 py-2">
                                <span className="sr-only">Aktionen</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {eintraege.map((e) =>
                            bearbeiten === e.id ? (
                                <tr
                                    key={e.id}
                                    className="border-t border-border"
                                >
                                    <td colSpan={8} className="px-3 py-3">
                                        <Formular
                                            liste={liste}
                                            farben={farben}
                                            eintrag={e}
                                            onFertig={() => setBearbeiten(null)}
                                        />
                                    </td>
                                </tr>
                            ) : (
                                <tr
                                    key={e.id}
                                    className="border-t border-border align-top"
                                >
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {e.key}
                                    </td>
                                    <td className="px-3 py-2">
                                        {e.abbreviation}
                                    </td>
                                    <td className="px-3 py-2">{e.name}</td>
                                    <td className="px-3 py-2">{e.label}</td>
                                    <td className="px-3 py-2">
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
                                            {e.label ||
                                                e.abbreviation ||
                                                e.name}
                                        </Badge>
                                        <span className="sr-only">
                                            {' '}
                                            Farbe {e.color ?? 'keine'}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 break-all">
                                        {e.url}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">
                                        {e.begriffe}
                                    </td>
                                    <td className="px-3 py-2 text-right whitespace-nowrap">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setBearbeiten(e.id)}
                                        >
                                            Bearbeiten
                                        </Button>{' '}
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={e.begriffe > 0}
                                            title={
                                                e.begriffe > 0
                                                    ? 'Wird noch verwendet'
                                                    : undefined
                                            }
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `«${e.name}» löschen?`,
                                                    )
                                                ) {
                                                    router.delete(
                                                        `/admin/listen/${liste}/${e.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }
                                            }}
                                        >
                                            Löschen
                                        </Button>
                                    </td>
                                </tr>
                            ),
                        )}
                        {eintraege.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={8}
                                    className="px-3 py-4 text-center text-muted-foreground"
                                >
                                    Noch keine Einträge.
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>
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
    const feld = (name: keyof Werte, label: string, disabled = false) => (
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
