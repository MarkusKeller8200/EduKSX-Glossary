import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DataGrid from '@/components/data-grid';
import IconAction from '@/components/icon-action';
import { Pencil } from 'lucide-react';

type Konto = {
    id: string;
    name: string;
    community_name: string | null;
    einrichtung: string;
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
                `${b.name} ${b.community_name ?? ''} ${b.email} ${b.einrichtung} ${b.rollen.join(' ')}`
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
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href="/admin/rollen">Rollen und Rechte</Link>
                    </Button>
                    <Button onClick={() => setNeu(!neu)}>Konto anlegen</Button>
                </div>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">
                {benutzer.length} Konten. Rollen und Sperren im Detail eines
                Kontos; jede Änderung steht im Protokoll.
            </p>

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

            <div className="mt-4">
                <DataGrid
                    id="admin.users"
                    caption="Benutzer"
                    rows={liste}
                    getRowId={(b: Konto) => b.id}
                    empty="Keine Treffer."
                    initialSort={{ id: 'name', desc: false }}
                    columns={[
                        {
                            id: 'name',
                            header: 'Name',
                            width: 220,
                            sticky: 'left',
                            className: 'font-medium',
                            sortValue: (b) => b.name,
                            cell: (b) => (
                                <>
                                    <Link
                                        href={`/admin/benutzer/${b.id}`}
                                        className="text-link underline-offset-4 hover:underline"
                                    >
                                        {b.name}
                                    </Link>
                                    {b.einrichtung ? (
                                        <span className="block font-normal text-muted-foreground">
                                            {b.einrichtung}
                                        </span>
                                    ) : null}
                                </>
                            ),
                        },
                        {
                            id: 'email',
                            header: 'E-Mail',
                            width: 240,
                            sortValue: (b) => b.email,
                            cell: (b) => b.email,
                        },
                        {
                            id: 'rollen',
                            header: 'Rollen',
                            width: 220,
                            sortValue: (b) => b.rollen.join(', '),
                            cell: (b) => (
                                <div className="flex flex-wrap gap-1">
                                    {b.rollen.length === 0 ? (
                                        <span className="text-muted-foreground">
                                            ohne Rolle
                                        </span>
                                    ) : (
                                        b.rollen.map((r) => (
                                            <Badge key={r} variant="outline">
                                                {r}
                                            </Badge>
                                        ))
                                    )}
                                </div>
                            ),
                        },
                        {
                            id: 'status',
                            header: 'Status',
                            width: 200,
                            sortValue: (b) =>
                                `${b.status}${b.zweiter_faktor ? '' : ' ohne2fa'}`,
                            cell: (b) => (
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
                            ),
                        },
                        {
                            id: 'aktionen',
                            label: 'Aktionen',
                            header: <span className="sr-only">Aktionen</span>,
                            width: 56,
                            fixed: true,
                            sticky: 'right',
                            className: 'py-1',
                            cell: (b) => (
                                <IconAction
                                    icon={Pencil}
                                    label={`${b.name}: Rollen und Konto`}
                                    href={`/admin/benutzer/${b.id}`}
                                />
                            ),
                        },
                    ]}
                />
            </div>
        </>
    );
}

function NeuesKonto({ onDone }: { onDone: () => void }) {
    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        password: '',
    });
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
                Neues Konto mit der Rolle Benutzer. Weitere Rollen danach im
                Detail vergeben. Das Passwort der Person mitteilen; sie kann es
                nach der Anmeldung ändern.
            </p>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="first_name">Vorname</Label>
                    <Input
                        id="first_name"
                        value={form.data.first_name}
                        onChange={(e) =>
                            form.setData('first_name', e.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.first_name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="last_name">Nachname</Label>
                    <Input
                        id="last_name"
                        value={form.data.last_name}
                        onChange={(e) =>
                            form.setData('last_name', e.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.last_name} />
                </div>
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
