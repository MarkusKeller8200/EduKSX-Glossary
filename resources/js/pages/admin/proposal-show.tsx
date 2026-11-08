import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { palette } from '@/lib/colors';
import { EREIGNIS, proposalStatusClass, wertText } from '@/lib/proposals';
import { cn } from '@/lib/utils';

type Vorschlag = {
    id: number;
    art: 'new' | 'change';
    begriff: { id: string; titel: string; revision: number } | null;
    basis: number | null;
    von: string;
    servicekonto: boolean;
    begruendung: string | null;
    status: string;
    status_label: string;
    offen: boolean;
    ergebnis: number | null;
    eingereicht: string | null;
};

type Zeile = {
    feld: string;
    label: string;
    basis: unknown;
    aktuell: unknown;
    vorschlag: unknown;
    konflikt: boolean;
    erledigt: boolean;
};

type Ereignis = {
    typ: string;
    am: string;
    von: string | null;
    text: string | null;
    details: Record<string, unknown> | null;
};

const OFFIZIELL = ['official_abbreviation', 'official_name'];

/** Vorschlag prüfen (#113): Vergleich Basis / aktuell / Vorschlag, annehmen, ablehnen, Rückfrage. */
export default function ProposalShow({
    vorschlag: v,
    vergleich,
    kategorien,
    ereignisse,
    offiziellErlaubt,
}: {
    vorschlag: Vorschlag;
    vergleich: Zeile[];
    kategorien: Record<string, string>;
    ereignisse: Ereignis[];
    offiziellErlaubt: boolean;
}) {
    const korrektur = v.art === 'change';
    const labels = (feld: string) => (feld === 'categories' ? kategorien : {});

    return (
        <>
            <Head title={`Vorschlag Nr. ${v.id}`} />
            <p className="text-sm">
                <Link
                    href="/admin/vorschlaege"
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← Vorschläge
                </Link>
            </p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                Vorschlag Nr. {v.id}{' '}
                <Badge
                    variant="outline"
                    className={proposalStatusClass(v.status)}
                >
                    {v.status_label}
                </Badge>
            </h1>
            <p className="mt-2 text-sm text-muted-foreground">
                {korrektur ? 'Korrektur zu ' : 'Neuer Begriff'}
                {v.begriff ? (
                    <Link
                        href={`/admin/begriffe/${v.begriff.id}`}
                        className="text-link underline-offset-4 hover:underline"
                    >
                        {v.begriff.titel}
                    </Link>
                ) : null}
                {korrektur && v.basis
                    ? ` · Basis V${v.basis}, Arbeitsstand Revision ${v.begriff?.revision ?? '–'}`
                    : ''}
                {' · von '}
                {v.von}
                {v.eingereicht
                    ? ` · ${new Date(v.eingereicht).toLocaleString('de-CH', { dateStyle: 'medium', timeStyle: 'short' })}`
                    : ''}
            </p>
            {v.begruendung ? (
                <p className="mt-3 max-w-prose rounded-md bg-muted px-3 py-2 text-sm whitespace-pre-wrap">
                    {v.begruendung}
                </p>
            ) : null}

            <Vergleich
                v={v}
                zeilen={vergleich}
                labels={labels}
                offiziellErlaubt={offiziellErlaubt}
            />

            {v.offen ? (
                <div className="mt-8 grid max-w-4xl gap-6 lg:grid-cols-2">
                    <Rueckfrage id={v.id} />
                    <Ablehnen id={v.id} />
                </div>
            ) : null}

            <h2 className="mt-8 text-lg font-semibold">Verlauf</h2>
            <ol className="mt-2 grid max-w-3xl gap-2 text-sm">
                {ereignisse.map((e, i) => (
                    <li key={i} className="rounded-lg border border-border p-3">
                        <span className="font-medium">
                            {EREIGNIS[e.typ] ?? e.typ}
                        </span>{' '}
                        <span className="text-muted-foreground">
                            · {e.von ?? '–'} ·{' '}
                            {new Date(e.am).toLocaleString('de-CH', {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            })}
                            {typeof e.details?.revision === 'number'
                                ? ` · Revision ${e.details.revision}`
                                : ''}
                        </span>
                        {e.text ? (
                            <p className="mt-1 whitespace-pre-wrap">{e.text}</p>
                        ) : null}
                    </li>
                ))}
            </ol>
        </>
    );
}

function Vergleich({
    v,
    zeilen,
    labels,
    offiziellErlaubt,
}: {
    v: Vorschlag;
    zeilen: Zeile[];
    labels: (feld: string) => Record<string, string>;
    offiziellErlaubt: boolean;
}) {
    const korrektur = v.art === 'change';
    // Vorgabe: alles übernehmen ausser Konflikten, Erledigtem und offiziellen Feldern ohne Recht
    // offizielle Felder: leer → Wert setzen geht ohne Recht (wie beim Bearbeiten)
    const gesperrt = (z: Zeile) =>
        korrektur &&
        OFFIZIELL.includes(z.feld) &&
        !offiziellErlaubt &&
        wertText(z.aktuell) !== '–';
    const form = useForm<{ felder: string[]; notiz: string }>({
        felder: zeilen
            .filter((z) => !z.konflikt && !z.erledigt && !gesperrt(z))
            .map((z) => z.feld),
        notiz: '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/admin/vorschlaege/${v.id}/annehmen`);
    };
    const konflikte = zeilen.filter((z) => z.konflikt).length;

    return (
        <form onSubmit={submit} className="mt-6">
            <h2 className="text-lg font-semibold">Änderungen</h2>
            {konflikte > 0 ? (
                <p
                    className={cn(
                        'mt-2 max-w-prose rounded-md border px-3 py-2 text-sm',
                        palette.amber.badge,
                    )}
                    role="alert"
                >
                    {konflikte === 1
                        ? '1 Feld wurde'
                        : `${konflikte} Felder wurden`}{' '}
                    seit dem Einreichen geändert (Konflikt). Bitte vergleichen
                    und nur bewusst übernehmen – sonst wird die spätere Änderung
                    überschrieben.
                </p>
            ) : null}
            <div className="mt-3 overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left">
                        <tr>
                            {v.offen ? (
                                <th className="w-10 px-3 py-2 font-medium">
                                    <span className="sr-only">Übernehmen</span>
                                </th>
                            ) : null}
                            <th className="px-3 py-2 font-medium">Feld</th>
                            {korrektur ? (
                                <>
                                    <th className="px-3 py-2 font-medium">
                                        Basis V{v.basis}
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Aktuell
                                    </th>
                                </>
                            ) : null}
                            <th className="px-3 py-2 font-medium">Vorschlag</th>
                        </tr>
                    </thead>
                    <tbody>
                        {zeilen.map((z) => (
                            <tr
                                key={z.feld}
                                className="border-t border-border align-top"
                            >
                                {v.offen ? (
                                    <td className="px-3 py-2">
                                        <Checkbox
                                            aria-label={`${z.label} übernehmen`}
                                            checked={form.data.felder.includes(
                                                z.feld,
                                            )}
                                            disabled={gesperrt(z)}
                                            onCheckedChange={(c) =>
                                                form.setData(
                                                    'felder',
                                                    c === true
                                                        ? [
                                                              ...form.data
                                                                  .felder,
                                                              z.feld,
                                                          ]
                                                        : form.data.felder.filter(
                                                              (f) =>
                                                                  f !== z.feld,
                                                          ),
                                                )
                                            }
                                        />
                                    </td>
                                ) : null}
                                <td className="px-3 py-2 font-medium">
                                    {z.label}
                                    {z.konflikt ? (
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                'ml-2',
                                                palette.amber.badge,
                                            )}
                                        >
                                            Konflikt
                                        </Badge>
                                    ) : null}
                                    {z.erledigt ? (
                                        <span className="block text-xs text-muted-foreground">
                                            schon so im Arbeitsstand
                                        </span>
                                    ) : null}
                                    {gesperrt(z) ? (
                                        <span className="block text-xs text-muted-foreground">
                                            offizielles Feld – Recht nötig
                                        </span>
                                    ) : null}
                                </td>
                                {korrektur ? (
                                    <>
                                        <td className="px-3 py-2 whitespace-pre-wrap text-muted-foreground">
                                            {wertText(z.basis, labels(z.feld))}
                                        </td>
                                        <td className="px-3 py-2 whitespace-pre-wrap">
                                            {wertText(
                                                z.aktuell,
                                                labels(z.feld),
                                            )}
                                        </td>
                                    </>
                                ) : null}
                                <td className="px-3 py-2 whitespace-pre-wrap">
                                    {wertText(z.vorschlag, labels(z.feld))}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {v.offen ? (
                <div className="mt-4 grid max-w-2xl gap-2">
                    <Label htmlFor="notiz">
                        Notiz an die einreichende Person (freiwillig)
                    </Label>
                    <Textarea
                        id="notiz"
                        rows={2}
                        maxLength={2000}
                        value={form.data.notiz}
                        onChange={(e) => form.setData('notiz', e.target.value)}
                    />
                    <InputError message={errors.felder} />
                    <div>
                        <Button
                            type="submit"
                            disabled={
                                form.processing || form.data.felder.length === 0
                            }
                        >
                            Gewählte Felder annehmen (Entwurf)
                        </Button>
                    </div>
                </div>
            ) : v.ergebnis ? (
                <p className="mt-3 text-sm text-muted-foreground">
                    Ergebnis: Revision {v.ergebnis}.
                </p>
            ) : null}
        </form>
    );
}

function Rueckfrage({ id }: { id: number }) {
    const form = useForm({ frage: '' });
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/admin/vorschlaege/${id}/rueckfrage`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-2 rounded-lg border border-border p-4"
        >
            <Label htmlFor="frage">Rückfrage</Label>
            <Textarea
                id="frage"
                rows={3}
                maxLength={2000}
                value={form.data.frage}
                onChange={(e) => form.setData('frage', e.target.value)}
                required
            />
            <InputError message={errors.frage} />
            <div>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                >
                    Rückfrage senden
                </Button>
            </div>
        </form>
    );
}

function Ablehnen({ id }: { id: number }) {
    const form = useForm({ begruendung: '' });
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/admin/vorschlaege/${id}/ablehnen`);
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-2 rounded-lg border border-border p-4"
        >
            <Label htmlFor="begruendung">Ablehnen mit Begründung</Label>
            <Textarea
                id="begruendung"
                rows={3}
                maxLength={2000}
                value={form.data.begruendung}
                onChange={(e) => form.setData('begruendung', e.target.value)}
                required
            />
            <InputError message={errors.begruendung} />
            <div>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                >
                    Ablehnen
                </Button>
            </div>
        </form>
    );
}
