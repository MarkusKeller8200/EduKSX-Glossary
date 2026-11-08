import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Wert = number | boolean | string;

type Einstellung = {
    key: string;
    gruppe: string;
    label: string;
    hinweis: string;
    typ: 'int' | 'bool' | 'repo' | 'url';
    min?: number;
    max?: number;
    einheit?: string;
    wert: Wert;
    vorgabe: Wert;
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
}: {
    einstellungen: Einstellung[];
    protokoll: Eintrag[];
}) {
    const form = useForm<{ werte: Record<string, Wert> }>({
        werte: Object.fromEntries(einstellungen.map((e) => [e.key, e.wert])),
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
                Allgemeine Einstellungen
            </h1>
            <p className="mt-2 max-w-prose text-sm text-muted-foreground">
                Werte, die ohne Neustart gelten. Geheimnisse (Mail,
                Microsoft-Anmeldung), Build-Angaben und die Version der
                Datenschutzerklärung bleiben in der Konfiguration. Jede Änderung
                steht im Protokoll.
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

function Feld({
    e,
    wert,
    onChange,
}: {
    e: Einstellung;
    wert: Wert;
    onChange: (v: Wert) => void;
}) {
    const id = `s-${e.key.replace(/\W/g, '-')}`;
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
                </label>
                {hint}
            </div>
        );
    }

    return (
        <div className="grid gap-1">
            <Label htmlFor={id}>{e.label}</Label>
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
