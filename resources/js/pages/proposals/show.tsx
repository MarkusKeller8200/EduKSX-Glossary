import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { EREIGNIS, proposalStatusClass, wertText } from '@/lib/proposals';
import type { VorschlagKurz } from '@/pages/proposals/index';

type Vorschlag = VorschlagKurz & {
    aenderungen: { label: string; vorschlag: unknown }[];
    begruendung: string | null;
    ereignisse: {
        typ: string;
        am: string;
        von: string;
        text: string | null;
    }[];
};

/** Eigener Vorschlag mit Verlauf, Antwort auf Rückfrage und Zurückziehen (#113). */
export default function ProposalShow({
    vorschlag: v,
}: {
    vorschlag: Vorschlag;
}) {
    const form = useForm({ antwort: '' });
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/vorschlaege/${v.id}/antwort`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title={`Vorschlag Nr. ${v.id}`} />
            <p className="text-sm">
                <Link
                    href="/vorschlaege"
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← Meine Vorschläge
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
                {v.art}
                {v.begriff ? (
                    <>
                        {' zu '}
                        <Link
                            href={`/begriffe/${v.begriff.slug}`}
                            className="text-link underline-offset-4 hover:underline"
                        >
                            {v.begriff.titel}
                        </Link>
                    </>
                ) : null}
            </p>

            <dl className="mt-6 grid max-w-3xl gap-x-6 gap-y-2 text-sm sm:grid-cols-[12rem_1fr]">
                {v.aenderungen.map((a) => (
                    <div key={a.label} className="contents">
                        <dt className="text-muted-foreground">{a.label}</dt>
                        <dd className="whitespace-pre-wrap">
                            {wertText(a.vorschlag)}
                        </dd>
                    </div>
                ))}
                {v.begruendung ? (
                    <>
                        <dt className="text-muted-foreground">Begründung</dt>
                        <dd className="whitespace-pre-wrap">{v.begruendung}</dd>
                    </>
                ) : null}
            </dl>

            <h2 className="mt-8 text-lg font-semibold">Verlauf</h2>
            <ol className="mt-2 grid max-w-3xl gap-2 text-sm">
                {v.ereignisse.map((e, i) => (
                    <li key={i} className="rounded-lg border border-border p-3">
                        <span className="font-medium">
                            {EREIGNIS[e.typ] ?? e.typ}
                        </span>{' '}
                        <span className="text-muted-foreground">
                            · {e.von} ·{' '}
                            {new Date(e.am).toLocaleString('de-CH', {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            })}
                        </span>
                        {e.text ? (
                            <p className="mt-1 whitespace-pre-wrap">{e.text}</p>
                        ) : null}
                    </li>
                ))}
            </ol>

            {v.status === 'query' ? (
                <form onSubmit={submit} className="mt-6 grid max-w-2xl gap-2">
                    <Label htmlFor="antwort">Antwort an die Redaktion</Label>
                    <Textarea
                        id="antwort"
                        rows={3}
                        maxLength={2000}
                        value={form.data.antwort}
                        onChange={(e) =>
                            form.setData('antwort', e.target.value)
                        }
                        required
                    />
                    <InputError message={errors.antwort} />
                    <div>
                        <Button type="submit" disabled={form.processing}>
                            Antwort senden
                        </Button>
                    </div>
                </form>
            ) : null}

            {v.offen ? (
                <div className="mt-6">
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.post(
                                `/vorschlaege/${v.id}/zurueckziehen`,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        Vorschlag zurückziehen
                    </Button>
                </div>
            ) : null}
        </>
    );
}
