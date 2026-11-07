import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Konto = {
    id: string;
    name: string;
    email: string;
    rollen: string[];
    status: string;
    zweiter_faktor: boolean;
};

/** Benutzerverwaltung (#38): Liste mit Suche und Filter, Konto anlegen. */
export default function Users({ benutzer }: { benutzer: Konto[] }) {
    const [suche, setSuche] = useState('');
    const [filter, setFilter] = useState<'alle' | 'gesperrt' | 'ohne2fa'>(
        'alle',
    );
    const [neu, setNeu] = useState(false);
    const q = suche.trim().toLowerCase();
    const liste = benutzer.filter(
        (b) =>
            (!q ||
                `${b.name} ${b.email} ${b.rollen.join(' ')}`
                    .toLowerCase()
                    .includes(q)) &&
            (filter === 'alle' ||
                (filter === 'gesperrt' && b.status === 'blocked') ||
                (filter === 'ohne2fa' && !b.zweiter_faktor)),
    );

    return (
        <>
            <Head title="Benutzer" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Benutzer
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {benutzer.length} Konten. Rollen und Sperren im Detail
                        eines Kontos; jede Änderung steht im Protokoll.
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href="/admin/rollen">Rollen und Rechte</Link>
                    </Button>
                    <Button onClick={() => setNeu(!neu)}>Konto anlegen</Button>
                </div>
            </div>

            {neu ? <NeuesKonto onDone={() => setNeu(false)} /> : null}

            <div className="mt-6 flex flex-wrap items-center gap-3">
                <div className="w-full max-w-sm">
                    <label htmlFor="suche" className="sr-only">
                        Benutzer filtern
                    </label>
                    <Input
                        id="suche"
                        type="search"
                        placeholder="Name, E-Mail oder Rolle …"
                        value={suche}
                        onChange={(e) => setSuche(e.target.value)}
                    />
                </div>
                {(
                    [
                        ['alle', 'Alle'],
                        ['gesperrt', 'Gesperrt'],
                        ['ohne2fa', 'Ohne zweiten Faktor'],
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

            <div className="mt-4 overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted text-left">
                        <tr>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Name
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                E-Mail
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Rollen
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {liste.map((b) => (
                            <tr
                                key={b.id}
                                className="border-t border-border align-top"
                            >
                                <td className="px-3 py-2 font-medium">
                                    <Link
                                        href={`/admin/benutzer/${b.id}`}
                                        className="text-link underline-offset-4 hover:underline"
                                    >
                                        {b.name}
                                    </Link>
                                </td>
                                <td className="px-3 py-2">{b.email}</td>
                                <td className="px-3 py-2">
                                    <div className="flex flex-wrap gap-1">
                                        {b.rollen.length === 0 ? (
                                            <span className="text-muted-foreground">
                                                Community
                                            </span>
                                        ) : (
                                            b.rollen.map((r) => (
                                                <Badge
                                                    key={r}
                                                    variant="outline"
                                                >
                                                    {r}
                                                </Badge>
                                            ))
                                        )}
                                    </div>
                                </td>
                                <td className="px-3 py-2">
                                    <div className="flex flex-wrap gap-1">
                                        {b.status === 'blocked' ? (
                                            <Badge variant="secondary">
                                                gesperrt
                                            </Badge>
                                        ) : (
                                            <span>aktiv</span>
                                        )}
                                        {b.zweiter_faktor ? null : (
                                            <Badge variant="outline">
                                                ohne zweiten Faktor
                                            </Badge>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {liste.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-3 py-6 text-center text-muted-foreground"
                                >
                                    Keine Treffer.
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>
        </>
    );
}

function NeuesKonto({ onDone }: { onDone: () => void }) {
    const form = useForm({ name: '', email: '', password: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/benutzer', { onSuccess: onDone });
    };

    return (
        <form
            onSubmit={submit}
            className="mt-6 grid max-w-xl gap-4 rounded-lg border border-border p-4"
        >
            <p className="text-sm text-muted-foreground">
                Neues Konto ohne Rolle (Community). Rollen danach im Detail
                vergeben. Das Passwort der Person mitteilen; sie kann es nach
                der Anmeldung ändern.
            </p>
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    required
                />
                <InputError message={form.errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="email">E-Mail</Label>
                <Input
                    id="email"
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    required
                />
                <InputError message={form.errors.email} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="password">Passwort (mind. 12 Zeichen)</Label>
                <Input
                    id="password"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    required
                />
                <InputError message={form.errors.password} />
            </div>
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    Anlegen
                </Button>
                <Button type="button" variant="outline" onClick={onDone}>
                    Abbrechen
                </Button>
            </div>
        </form>
    );
}
