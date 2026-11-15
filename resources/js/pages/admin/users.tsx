import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DataGrid from '@/components/data-grid';
import IconAction from '@/components/icon-action';
import { Pencil } from 'lucide-react';
import UserAdminTabs from '@/components/user-admin-tabs';

type Konto = {
    id: string;
    name: string;
    community_name: string | null;
    einrichtung: string;
    email: string;
    rollen: string[];
    status: string;
    zweiter_faktor: boolean;
    organisation: { id: string; name: string; rolle: string } | null;
    letzte_anmeldung: string | null;
    angelegt: string | null;
};

const GRUPPIERT = 'glossary.admin.users.gruppiert';

/** Benutzerverwaltung (#38): Liste mit Suche und Filter, Konto anlegen. */
export default function Users({ benutzer }: { benutzer: Konto[] }) {
    const [suche, setSuche] = useState('');
    // Vorgabe «Aktive»: gesperrte Konten nur auf Wunsch
    const [filter, setFilter] = useState<
        'aktive' | 'alle' | 'gesperrt' | 'ohne2fa'
    >('aktive');
    // optional nach Organisation gruppiert, Gruppen anfangs eingeklappt (gemerkt im Browser)
    const [gruppiert, setGruppiert] = useState<boolean>(() => {
        try {
            return localStorage.getItem(GRUPPIERT) === '1';
        } catch {
            return false;
        }
    });
    const gruppieren = (an: boolean) => {
        setGruppiert(an);
        try {
            localStorage.setItem(GRUPPIERT, an ? '1' : '0');
        } catch {
            // ohne Speicher im Browser: nur für diese Seite
        }
    };
    const [neu, setNeu] = useState(false);
    const gesperrt = benutzer.filter((b) => b.status === 'blocked').length;
    const q = suche.trim().toLowerCase();
    const liste = useMemo(
        () =>
            benutzer.filter(
                (b) =>
                    (!q ||
                        `${b.name} ${b.community_name ?? ''} ${b.email} ${b.einrichtung} ${b.organisation?.name ?? ''} ${b.rollen.join(' ')}`
                            .toLowerCase()
                            .includes(q)) &&
                    (filter === 'alle' ||
                        (filter === 'aktive' && b.status !== 'blocked') ||
                        (filter === 'gesperrt' && b.status === 'blocked') ||
                        (filter === 'ohne2fa' && !b.zweiter_faktor)),
            ),
        [benutzer, q, filter],
    );
    const gruppen = useMemo(() => {
        if (!gruppiert) {
            return undefined;
        }
        const map = new Map<string, Konto[]>();
        for (const b of liste) {
            const key = b.organisation?.name ?? '';
            map.set(key, [...(map.get(key) ?? []), b]);
        }

        return [...map.entries()]
            .sort(([a], [b]) =>
                a === '' ? 1 : b === '' ? -1 : a.localeCompare(b, 'de-CH'),
            )
            .map(([key, rows]) => ({
                key: key || '—',
                title: key || 'Ohne Organisation',
                rows,
            }));
    }, [liste, gruppiert]);

    return (
        <>
            <Head title="Benutzer" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Benutzer und Organisationen
                    </h1>
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href="/admin/rollen">Rollen und Rechte</Link>
                    </Button>
                    <Button onClick={() => setNeu(!neu)}>Konto anlegen</Button>
                </div>
            </div>
            <UserAdminTabs aktiv="benutzer" />
            <p className="mt-4 text-sm text-muted-foreground">
                {benutzer.length} Konten
                {gesperrt > 0 ? `, davon ${gesperrt} gesperrt` : ''}. Rollen und
                Sperren im Detail eines Kontos; jede Änderung steht im
                Protokoll.
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
                        placeholder="Name, E-Mail, Organisation oder Rolle …"
                        value={suche}
                        onChange={(e) => setSuche(e.target.value)}
                    />
                </div>
                {(
                    [
                        ['aktive', 'Aktive'],
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
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={gruppiert}
                        onCheckedChange={(v) => gruppieren(v === true)}
                    />
                    Nach Organisation gruppieren
                </label>
            </div>

            <div className="mt-4">
                <DataGrid
                    id="admin.users"
                    caption="Benutzer"
                    rows={gruppen ? undefined : liste}
                    groups={gruppen}
                    collapsible={gruppiert}
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
                            id: 'organisation',
                            header: 'Organisation',
                            width: 220,
                            sortValue: (b) => b.organisation?.name ?? '',
                            cell: (b) =>
                                b.organisation ? (
                                    <>
                                        <Link
                                            href={`/admin/organisationen/${b.organisation.id}`}
                                            className="text-link underline-offset-4 hover:underline"
                                        >
                                            {b.organisation.name}
                                        </Link>
                                        <span className="block text-muted-foreground">
                                            {b.organisation.rolle}
                                        </span>
                                    </>
                                ) : (
                                    <span className="text-muted-foreground">
                                        –
                                    </span>
                                ),
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
                            id: 'letzte_anmeldung',
                            header: 'Letzte Anmeldung',
                            width: 150,
                            sortValue: (b) => b.letzte_anmeldung ?? '',
                            cell: (b) => b.letzte_anmeldung ?? '–',
                        },
                        {
                            id: 'angelegt',
                            header: 'Angelegt',
                            width: 120,
                            sortValue: (b) => b.angelegt ?? '',
                            cell: (b) => b.angelegt ?? '–',
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
