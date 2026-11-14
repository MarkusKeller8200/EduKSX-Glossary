import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';

type Wert = number | boolean | string;

type Einstellung = {
    key: string;
    gruppe: string;
    label: string;
    hinweis: string;
    typ: 'int' | 'bool' | 'repo' | 'url' | 'choice' | 'secret';
    optionen?: Record<string, string>;
    min?: number;
    max?: number;
    einheit?: string;
    wert: Wert;
    vorgabe: Wert;
    gesetzt: boolean | null;
    gesperrt: string | null;
    herkunft: 'Umgebung' | 'Administration' | 'Vorgabe';
};

type Umgebung = {
    name: string;
    gruppe: string;
    geheim: boolean;
    bedeutung: string;
    gesetzt: boolean;
    wert: string | null;
};

type Eintrag = {
    am: string;
    von: string;
    key: string;
    alt: Wert | null;
    neu: Wert | null;
};

const text = (v: Wert | null) =>
    typeof v === 'boolean'
        ? v
            ? 'ein'
            : 'aus'
        : v === '' || v === null
          ? '–'
          : String(v);

/** Allgemeine Einstellungen (#106): Löschfristen, Quellenprüfung, Freigabe, Hinweise und Links. */
export default function Settings({
    einstellungen,
    protokoll,
    neuesGeheimnis,
    umgebung,
    ordner,
}: {
    einstellungen: Einstellung[];
    protokoll: Eintrag[];
    neuesGeheimnis: { key: string; wert: string } | null;
    umgebung: Umgebung[] | null;
    ordner: string | null;
}) {
    // gesperrte Werte (Umgebung) und Geheimnisse gehen nicht über das Formular
    const form = useForm<{ werte: Record<string, Wert> }>({
        werte: Object.fromEntries(
            einstellungen
                .filter((e) => e.typ !== 'secret' && e.gesperrt === null)
                .map((e) => [e.key, e.wert]),
        ),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const set = (key: string, v: Wert) =>
        form.setData('werte', { ...form.data.werte, [key]: v });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/admin/einstellungen', { preserveScroll: true });
    };
    const gruppen = [...new Set(einstellungen.map((e) => e.gruppe))];
    const labels = Object.fromEntries(
        einstellungen.map((e) => [e.key, e.label]),
    );

    return (
        <>
            <Head title="Einstellungen" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Einstellungen
            </h1>
            <p className="mt-2 max-w-prose text-sm text-muted-foreground">
                Werte hier gelten sofort, ohne Neustart, und stehen im
                Protokoll. Ist ein Wert in der Umgebung (<code>.env</code>)
                gesetzt, hat er Vorrang und ist hier gesperrt.
                {umgebung !== null
                    ? ' Die Umgebung selbst steht unten (nur ansehen).'
                    : ''}
            </p>

            <form onSubmit={submit} className="mt-6 grid max-w-3xl gap-8">
                {gruppen.map((g) => (
                    <fieldset key={g} className="grid gap-4">
                        <legend className="mb-2 text-lg font-semibold">
                            {g}
                        </legend>
                        {einstellungen
                            .filter((e) => e.gruppe === g)
                            .map((e) => (
                                <Feld
                                    key={e.key}
                                    e={e}
                                    wert={form.data.werte[e.key]}
                                    onChange={(v) => set(e.key, v)}
                                    neu={
                                        neuesGeheimnis?.key === e.key
                                            ? neuesGeheimnis.wert
                                            : null
                                    }
                                />
                            ))}
                    </fieldset>
                ))}
                <InputError message={errors.werte} />
                <div>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Speichern
                    </Button>
                </div>
            </form>

            {umgebung !== null ? (
                <UmgebungListe umgebung={umgebung} ordner={ordner ?? ''} />
            ) : null}

            <h2 className="mt-10 text-lg font-semibold">Protokoll</h2>
            {protokoll.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Noch keine Änderungen – es gelten die Vorgaben.
                </p>
            ) : (
                <ul className="mt-2 grid max-w-3xl gap-1 text-sm">
                    {protokoll.map((p, i) => (
                        <li key={i}>
                            <span className="text-muted-foreground">
                                {new Date(p.am).toLocaleString('de-CH', {
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                })}{' '}
                                · {p.von}:
                            </span>{' '}
                            {labels[p.key] ?? p.key}: {text(p.alt)} →{' '}
                            {text(p.neu)}
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

function UmgebungListe({
    umgebung,
    ordner,
}: {
    umgebung: Umgebung[];
    ordner: string;
}) {
    const gruppen = [...new Set(umgebung.map((u) => u.gruppe))];

    return (
        <section className="mt-12 max-w-4xl">
            <h2 className="text-lg font-semibold">
                Umgebung (<code>.env</code>) – nur ansehen
            </h2>
            <p className="mt-1 max-w-prose text-sm text-muted-foreground">
                Diese Werte liest die Anwendung beim Start der Container. Aus
                Sicherheitsgründen lassen sie sich nicht im Browser ändern;
                Geheimnisse erscheinen nur als «gesetzt».
            </p>
            <pre className="mt-3 overflow-x-auto rounded-md bg-muted p-3 text-xs">
                {`cd ${ordner}\nnano .env                # Wert ändern bzw. ergänzen\ndocker compose up -d     # übernehmen (restart genügt nicht)`}
            </pre>
            {gruppen.map((g) => (
                <div key={g} className="mt-5">
                    <h3 className="text-sm font-semibold">{g}</h3>
                    <table className="mt-2 w-full text-sm">
                        <tbody>
                            {umgebung
                                .filter((u) => u.gruppe === g)
                                .map((u) => (
                                    <tr
                                        key={u.name}
                                        className="border-b align-top"
                                    >
                                        <td className="py-1.5 pr-3 font-mono text-xs whitespace-nowrap">
                                            {u.name}
                                        </td>
                                        <td className="py-1.5 pr-3 break-all">
                                            {!u.gesetzt ? (
                                                <span className="text-muted-foreground">
                                                    nicht gesetzt (Vorgabe)
                                                </span>
                                            ) : u.geheim ? (
                                                <span className="text-emerald-700 dark:text-emerald-400">
                                                    gesetzt
                                                </span>
                                            ) : (
                                                <code>{u.wert}</code>
                                            )}
                                        </td>
                                        <td className="py-1.5 text-muted-foreground">
                                            {u.bedeutung}
                                        </td>
                                    </tr>
                                ))}
                        </tbody>
                    </table>
                </div>
            ))}
        </section>
    );
}

function Feld({
    e,
    wert,
    onChange,
    neu,
}: {
    e: Einstellung;
    wert: Wert;
    onChange: (v: Wert) => void;
    neu: string | null;
}) {
    const id = `s-${e.key.replace(/\W/g, '-')}`;
    const herkunft = (
        <span className="ml-2 rounded bg-muted px-1.5 py-0.5 text-xs font-normal text-muted-foreground">
            {e.herkunft}
        </span>
    );

    if (e.gesperrt !== null) {
        return (
            <div className="grid gap-1">
                <span className="text-sm font-medium">
                    {e.label}
                    {herkunft}
                </span>
                <p className="text-sm">
                    {e.typ === 'secret' ? 'gesetzt' : text(e.wert)}
                </p>
                <p className="text-xs text-muted-foreground">
                    Aus der Umgebung (<code>{e.gesperrt}</code>) – ändern in der{' '}
                    <code>.env</code> des Servers, danach{' '}
                    <code>docker compose up -d</code>. {e.hinweis}
                </p>
            </div>
        );
    }

    if (e.typ === 'secret') {
        const senden = (aktion: 'erzeugen' | 'entfernen') =>
            router.post(
                `/admin/einstellungen/geheim/${e.key}`,
                { aktion },
                { preserveScroll: true },
            );

        return (
            <div className="grid gap-2">
                <span className="text-sm font-medium">
                    {e.label}
                    {herkunft}
                </span>
                <p className="text-sm">
                    {e.gesetzt ? 'gesetzt' : 'nicht gesetzt'}
                </p>
                {neu !== null ? (
                    <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-700 dark:bg-amber-950">
                        Neuer Wert – jetzt kopieren, er wird nicht mehr
                        angezeigt:
                        <code className="mt-1 block break-all select-all">
                            {neu}
                        </code>
                    </div>
                ) : null}
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => senden('erzeugen')}
                    >
                        {e.gesetzt ? 'Neu erzeugen' : 'Erzeugen'}
                    </Button>
                    {e.gesetzt ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => senden('entfernen')}
                        >
                            Entfernen
                        </Button>
                    ) : null}
                </div>
                <p className="text-xs text-muted-foreground">{e.hinweis}</p>
            </div>
        );
    }
    const hint = (
        <p id={`${id}-hint`} className="text-xs text-muted-foreground">
            {e.hinweis} Vorgabe: {text(e.vorgabe)}
            {e.einheit && e.typ === 'int' ? ` ${e.einheit}` : ''}.
        </p>
    );

    if (e.typ === 'bool') {
        return (
            <div className="grid gap-1">
                <label htmlFor={id} className="flex items-center gap-3 text-sm">
                    <Checkbox
                        id={id}
                        checked={wert === true}
                        aria-describedby={`${id}-hint`}
                        onCheckedChange={(v) => onChange(v === true)}
                    />
                    {e.label}
                    {herkunft}
                </label>
                {hint}
            </div>
        );
    }

    if (e.typ === 'choice') {
        return (
            <div className="grid gap-1">
                <Label htmlFor={id}>
                    {e.label}
                    {herkunft}
                </Label>
                <NativeSelect
                    id={id}
                    className="max-w-md"
                    aria-describedby={`${id}-hint`}
                    value={String(wert)}
                    onChange={(ev) => onChange(ev.target.value)}
                >
                    {Object.entries(e.optionen ?? {}).map(([k, l]) => (
                        <option key={k} value={k}>
                            {l}
                        </option>
                    ))}
                </NativeSelect>
                {hint}
            </div>
        );
    }

    return (
        <div className="grid gap-1">
            <Label htmlFor={id}>
                {e.label}
                {herkunft}
            </Label>
            <div className="flex items-center gap-2">
                <Input
                    id={id}
                    type={e.typ === 'int' ? 'number' : 'text'}
                    min={e.min}
                    max={e.max}
                    className={e.typ === 'int' ? 'w-28' : 'max-w-md'}
                    aria-describedby={`${id}-hint`}
                    value={String(wert)}
                    onChange={(ev) =>
                        onChange(
                            e.typ === 'int'
                                ? Number(ev.target.value)
                                : ev.target.value,
                        )
                    }
                />
                {e.einheit ? (
                    <span className="text-sm text-muted-foreground">
                        {e.einheit}
                    </span>
                ) : null}
            </div>
            {hint}
        </div>
    );
}
