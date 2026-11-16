import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import UserAdminTabs from '@/components/user-admin-tabs';

type Anfrage = {
    id: number;
    konto: { id: string; name: string; email: string };
    organisation: string | null;
    begruendung: string;
    status: 'open' | 'accepted' | 'rejected';
    am: string | null;
    entschieden_am: string | null;
    entschieden_von: string | null;
    hinweis: string | null;
    voraussetzungen: string[];
};

const STATUS: Record<Anfrage['status'], string> = {
    open: 'offen',
    accepted: 'angenommen',
    rejected: 'abgelehnt',
};

/** Bewerbungen als Moderator/in (#460): alle Anfragen; annehmen oder ablehnen nur Admins. */
export default function RoleRequests({ anfragen }: { anfragen: Anfrage[] }) {
    const [filter, setFilter] = useState<'offen' | 'alle'>('offen');
    const liste =
        filter === 'offen'
            ? anfragen.filter((a) => a.status === 'open')
            : anfragen;
    const offen = anfragen.filter((a) => a.status === 'open').length;

    return (
        <>
            <Head title="Anfragen" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Benutzer und Organisationen
            </h1>
            <UserAdminTabs aktiv="anfragen" />
            <p className="mt-4 text-sm text-muted-foreground">
                Bewerbungen als Moderator/in. Beim Annehmen erhält das Konto die
                Rolle Moderator; die Person wird per Mail informiert. Ablehnen
                mit kurzer Begründung.
            </p>
            <div className="mt-4 flex gap-2">
                {(
                    [
                        ['offen', `Offen (${offen})`],
                        ['alle', `Alle (${anfragen.length})`],
                    ] as const
                ).map(([wert, text]) => (
                    <Button
                        key={wert}
                        size="sm"
                        variant={filter === wert ? 'default' : 'outline'}
                        aria-pressed={filter === wert}
                        onClick={() => setFilter(wert)}
                    >
                        {text}
                    </Button>
                ))}
            </div>
            {liste.length === 0 ? (
                <p className="mt-6 rounded-lg border border-border px-3 py-6 text-center text-muted-foreground">
                    {filter === 'offen'
                        ? 'Keine offenen Anfragen.'
                        : 'Noch keine Anfragen.'}
                </p>
            ) : (
                <ul className="mt-4 grid gap-3">
                    {liste.map((a) => (
                        <Eintrag key={a.id} anfrage={a} />
                    ))}
                </ul>
            )}
        </>
    );
}

function Eintrag({ anfrage: a }: { anfrage: Anfrage }) {
    const form = useForm({ hinweis: '' });
    const entscheiden = (annehmen: boolean) =>
        form.post(
            `/admin/anfragen/${a.id}/${annehmen ? 'annehmen' : 'ablehnen'}`,
            { preserveScroll: true },
        );

    return (
        <li className="rounded-lg border border-border p-4">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <Link
                        href={`/admin/benutzer/${a.konto.id}`}
                        className="font-medium text-link underline-offset-4 hover:underline"
                    >
                        {a.konto.name}
                    </Link>{' '}
                    <span className="text-sm text-muted-foreground">
                        {a.konto.email}
                        {a.organisation ? ` · ${a.organisation}` : ''}
                    </span>
                </div>
                <div className="flex items-center gap-2 text-sm">
                    <span className="text-muted-foreground">{a.am}</span>
                    <Badge
                        variant={
                            a.status === 'open'
                                ? 'default'
                                : a.status === 'accepted'
                                  ? 'secondary'
                                  : 'outline'
                        }
                    >
                        {STATUS[a.status]}
                    </Badge>
                </div>
            </div>
            <p className="mt-2 text-sm whitespace-pre-line">{a.begruendung}</p>
            {a.status !== 'open' ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    {STATUS[a.status]} am {a.entschieden_am}
                    {a.entschieden_von ? ` von ${a.entschieden_von}` : ''}
                    {a.hinweis ? `: ${a.hinweis}` : ''}
                </p>
            ) : (
                <div className="mt-3 grid gap-2">
                    {a.voraussetzungen.length > 0 ? (
                        <p className="text-sm text-destructive-foreground">
                            Inzwischen fehlt: {a.voraussetzungen.join(', ')}.
                        </p>
                    ) : null}
                    <Label htmlFor={`hinweis-${a.id}`}>
                        Hinweis an die Person (beim Ablehnen Pflicht)
                    </Label>
                    <Textarea
                        id={`hinweis-${a.id}`}
                        rows={2}
                        maxLength={1000}
                        value={form.data.hinweis}
                        onChange={(e) =>
                            form.setData('hinweis', e.target.value)
                        }
                    />
                    <InputError message={form.errors.hinweis} />
                    <div className="flex gap-2">
                        <Button
                            size="sm"
                            disabled={form.processing}
                            onClick={() => entscheiden(true)}
                        >
                            Annehmen
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => entscheiden(false)}
                        >
                            Ablehnen
                        </Button>
                    </div>
                </div>
            )}
        </li>
    );
}
