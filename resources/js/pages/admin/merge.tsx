import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { stateClasses } from '@/lib/colors';
import { confirmAction } from '@/components/confirm-dialog';

type Begriff = {
    id: string;
    titel: string;
    veroeffentlicht: boolean;
    werte: Record<string, string>;
    kategorien: string[];
    herkunft: string[];
    aliase: string[];
};

const OFFIZIELL = ['official_abbreviation', 'official_name'];

/** Je Feld den Begriff mit Wert wählen: zuerst den verbleibenden, sonst den ersten mit Inhalt. */
function vorgaben(
    begriffe: Begriff[],
    felder: string[],
    ziel: string,
): Record<string, string> {
    const zielBegriff = begriffe.find((b) => b.id === ziel);
    const out: Record<string, string> = {};
    felder.forEach((f) => {
        const mitWert =
            zielBegriff && zielBegriff.werte[f] !== ''
                ? zielBegriff
                : begriffe.find((b) => b.werte[f] !== '');
        out[f] = (mitWert ?? zielBegriff)?.id ?? ziel;
    });
    return out;
}

/** Zusammenführen (#16): welcher Begriff bleibt, welche Texte gelten; Listen werden vereinigt. */
export default function Merge({
    begriffe,
    felder,
    offiziellErlaubt,
}: {
    begriffe: Begriff[];
    felder: Record<string, string>;
    offiziellErlaubt: boolean;
}) {
    const keys = Object.keys(felder);
    const start = begriffe[0].id;
    const form = useForm<{
        ziel: string;
        ids: string[];
        auswahl: Record<string, string>;
    }>({
        ziel: start,
        ids: begriffe.map((b) => b.id),
        auswahl: vorgaben(begriffe, keys, start),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const unterschiedlich = keys.filter(
        (f) => new Set(begriffe.map((b) => b.werte[f])).size > 1,
    );
    const gleich = keys.filter(
        (f) => !unterschiedlich.includes(f) && begriffe[0].werte[f] !== '',
    );
    const vereinigt = (liste: (b: Begriff) => string[]) => [
        ...new Set(begriffe.flatMap(liste)),
    ];
    const zielTitel = begriffe.find((b) => b.id === form.data.ziel)?.titel;

    const zielSetzen = (id: string) =>
        form.setData({
            ...form.data,
            ziel: id,
            auswahl: vorgaben(begriffe, keys, id),
        });
    const submit = async (e: FormEvent) => {
        e.preventDefault();
        if (
            await confirmAction(
                `${begriffe.length} Begriffe zu «${zielTitel}» zusammenführen? Die anderen werden zur Weiterleitung.`,
            )
        ) {
            form.post('/admin/doppelte/zusammenfuehren');
        }
    };

    return (
        <>
            <Head title="Zusammenführen" />
            <p className="text-sm">
                <Link
                    href="/admin/doppelte"
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← Doppelte
                </Link>
            </p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                Begriffe zusammenführen
            </h1>
            <p className="mt-2 text-sm text-muted-foreground">
                Ein Begriff bleibt mit seiner ID und Adresse erhalten, die
                anderen leiten danach auf ihn weiter. Kategorien, Herkunft,
                Aliase und «Intern genutzt in» werden vereinigt; Abkürzungen und
                Bezeichnungen der anderen bleiben als Suchbegriffe auffindbar.
                War einer der Begriffe veröffentlicht, wird das Ergebnis gleich
                veröffentlicht.
            </p>

            {errors.form || errors.ziel || errors.ids ? (
                <Alert variant="destructive" className="mt-6">
                    <AlertDescription>
                        {errors.form ?? errors.ziel ?? errors.ids}
                    </AlertDescription>
                </Alert>
            ) : null}

            <form onSubmit={submit} className="mt-6">
                <div className="overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted text-left align-top">
                            <tr>
                                <th
                                    scope="col"
                                    className="sticky left-0 z-10 w-48 bg-muted px-3 py-2 font-medium"
                                >
                                    Feld
                                </th>
                                {begriffe.map((b) => (
                                    <th
                                        key={b.id}
                                        scope="col"
                                        className="px-3 py-2 font-medium"
                                    >
                                        <span translate="no">{b.titel}</span>
                                        <span className="mt-1 block">
                                            <Badge
                                                variant="outline"
                                                className={
                                                    b.veroeffentlicht
                                                        ? stateClasses.published
                                                        : stateClasses.draft
                                                }
                                            >
                                                {b.veroeffentlicht
                                                    ? 'veröffentlicht'
                                                    : 'Entwurf'}
                                            </Badge>
                                        </span>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            <tr className="border-t border-border bg-secondary/40">
                                <th
                                    scope="row"
                                    className="sticky left-0 z-10 bg-background px-3 py-2 text-left font-medium shadow-[inset_-1px_0_0_var(--border)]"
                                >
                                    Bleibt erhalten
                                </th>
                                {begriffe.map((b) => (
                                    <td key={b.id} className="px-3 py-2">
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                name="ziel"
                                                value={b.id}
                                                checked={
                                                    form.data.ziel === b.id
                                                }
                                                onChange={() =>
                                                    zielSetzen(b.id)
                                                }
                                            />
                                            dieser Begriff
                                        </label>
                                    </td>
                                ))}
                            </tr>
                            {unterschiedlich.map((f) => {
                                const gesperrt =
                                    !offiziellErlaubt && OFFIZIELL.includes(f);
                                return (
                                    <tr
                                        key={f}
                                        className="border-t border-border align-top"
                                    >
                                        <th
                                            scope="row"
                                            className="sticky left-0 z-10 bg-background px-3 py-2 text-left font-medium shadow-[inset_-1px_0_0_var(--border)]"
                                        >
                                            {felder[f]}
                                        </th>
                                        {begriffe.map((b) => (
                                            <td
                                                key={b.id}
                                                className="px-3 py-2"
                                            >
                                                <label className="flex items-start gap-2">
                                                    <input
                                                        type="radio"
                                                        className="mt-1"
                                                        name={`auswahl-${f}`}
                                                        value={b.id}
                                                        disabled={gesperrt}
                                                        checked={
                                                            form.data.auswahl[
                                                                f
                                                            ] === b.id
                                                        }
                                                        onChange={() =>
                                                            form.setData(
                                                                'auswahl',
                                                                {
                                                                    ...form.data
                                                                        .auswahl,
                                                                    [f]: b.id,
                                                                },
                                                            )
                                                        }
                                                    />
                                                    <span
                                                        className="whitespace-pre-line"
                                                        translate="no"
                                                    >
                                                        {b.werte[f] || (
                                                            <span className="text-muted-foreground">
                                                                (leer)
                                                            </span>
                                                        )}
                                                    </span>
                                                </label>
                                            </td>
                                        ))}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <dl className="mt-6 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[14rem_1fr]">
                    {gleich.map((f) => (
                        <div key={f} className="contents">
                            <dt className="text-muted-foreground">
                                {felder[f]} (bei allen gleich)
                            </dt>
                            <dd translate="no">{begriffe[0].werte[f]}</dd>
                        </div>
                    ))}
                    <dt className="text-muted-foreground">
                        Kategorien (vereinigt)
                    </dt>
                    <dd>{vereinigt((b) => b.kategorien).join(', ') || '–'}</dd>
                    <dt className="text-muted-foreground">
                        Herkunft (vereinigt)
                    </dt>
                    <dd>{vereinigt((b) => b.herkunft).join(', ') || '–'}</dd>
                    <dt className="text-muted-foreground">
                        Aliase (vereinigt)
                    </dt>
                    <dd translate="no">
                        {vereinigt((b) => b.aliase).join(', ') || '–'}
                    </dd>
                </dl>
                <InputError className="mt-2" message={errors.auswahl} />

                <div className="mt-6 flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Zusammenführen zu «{zielTitel}»
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/admin/doppelte">Abbrechen</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}
