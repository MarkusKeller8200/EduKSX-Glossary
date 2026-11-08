import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DataGrid from '@/components/data-grid';
import IconAction from '@/components/icon-action';
import { KeyRound } from 'lucide-react';

type Konto = {
    id: string;
    name: string;
    zweck: string;
    verantwortlich: string | null;
    status: string;
    scopes: string[];
    aktive_token: number;
};

/** Servicekonten (#47): technische Integrationen ohne Login, mit Token. */
export default function ServiceAccounts({
    konten,
    scopes,
}: {
    konten: Konto[];
    scopes: Record<string, string>;
}) {
    return (
        <>
            <Head title="Servicekonten" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Servicekonten
            </h1>
            <p className="mt-2 max-w-prose text-sm text-muted-foreground">
                Für Integrationen wie das Intranet oder einen MCP-Adapter. Ein
                Servicekonto kann sich nicht anmelden; es arbeitet nur mit
                Token. Token werden einmal angezeigt, sind befristet und einzeln
                widerrufbar. Lesen braucht kein Token – die öffentliche API ist
                frei.
            </p>

            {konten.length === 0 ? (
                <p className="mt-6 text-sm text-muted-foreground">
                    Noch keine Servicekonten.
                </p>
            ) : (
                <div className="mt-6">
                    <DataGrid
                        id="admin.service-accounts"
                        caption="Servicekonten"
                        rows={konten}
                        getRowId={(k: Konto) => k.id}
                        columns={[
                            {
                                id: 'name',
                                header: 'Name',
                                width: 200,
                                sticky: 'left',
                                sortValue: (k) => k.name,
                                cell: (k) => (
                                    <>
                                        <Link
                                            href={`/admin/servicekonten/${k.id}`}
                                            className="font-medium text-link underline-offset-4 hover:underline"
                                        >
                                            {k.name}
                                        </Link>
                                        {k.status !== 'active' ? (
                                            <Badge
                                                variant="outline"
                                                className="ml-2"
                                            >
                                                gesperrt
                                            </Badge>
                                        ) : null}
                                    </>
                                ),
                            },
                            {
                                id: 'zweck',
                                header: 'Zweck',
                                width: 280,
                                sortValue: (k) => k.zweck,
                                cell: (k) => k.zweck,
                            },
                            {
                                id: 'verantwortlich',
                                header: 'Verantwortlich',
                                width: 180,
                                sortValue: (k) => k.verantwortlich,
                                cell: (k) => k.verantwortlich ?? '–',
                            },
                            {
                                id: 'scopes',
                                header: 'Scopes',
                                width: 120,
                                sortValue: (k) => k.scopes.join(', '),
                                cell: (k) => k.scopes.join(', ') || '–',
                            },
                            {
                                id: 'token',
                                header: 'Token',
                                width: 110,
                                sortValue: (k) => k.aktive_token,
                                cell: (k) => `${k.aktive_token} gültig`,
                            },
                            {
                                id: 'aktionen',
                                label: 'Aktionen',
                                header: (
                                    <span className="sr-only">Aktionen</span>
                                ),
                                width: 56,
                                fixed: true,
                                sticky: 'right',
                                className: 'py-1',
                                cell: (k) => (
                                    <IconAction
                                        icon={KeyRound}
                                        label={`${k.name}: Token und Protokoll`}
                                        href={`/admin/servicekonten/${k.id}`}
                                    />
                                ),
                            },
                        ]}
                    />
                </div>
            )}

            <NeuesKonto scopes={scopes} />
        </>
    );
}

function NeuesKonto({ scopes }: { scopes: Record<string, string> }) {
    const form = useForm<{ name: string; zweck: string; scopes: string[] }>({
        name: '',
        zweck: '',
        scopes: [],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/servicekonten');
    };

    return (
        <form
            onSubmit={submit}
            className="mt-8 grid max-w-xl gap-4 rounded-lg border border-border p-4"
        >
            <h2 className="text-lg font-semibold">Neues Servicekonto</h2>
            <p className="text-sm text-muted-foreground">
                Verantwortlich sind Sie. Scopes nur im Rahmen Ihrer eigenen
                Rechte.
            </p>
            <div className="grid gap-2">
                <Label htmlFor="sk-name">Name</Label>
                <Input
                    id="sk-name"
                    placeholder="z. B. Intranet Sek Bülach"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="sk-zweck">Zweck</Label>
                <Input
                    id="sk-zweck"
                    placeholder="z. B. Vorschläge aus dem Intranet übermitteln"
                    value={form.data.zweck}
                    onChange={(e) => form.setData('zweck', e.target.value)}
                    required
                />
                <InputError message={errors.zweck} />
            </div>
            <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">Scopes</legend>
                {Object.entries(scopes).map(([key, label]) => (
                    <label key={key} className="flex items-start gap-3 text-sm">
                        <Checkbox
                            checked={form.data.scopes.includes(key)}
                            onCheckedChange={(v) =>
                                form.setData(
                                    'scopes',
                                    v === true
                                        ? [...form.data.scopes, key]
                                        : form.data.scopes.filter(
                                              (s) => s !== key,
                                          ),
                                )
                            }
                        />
                        <span>
                            <code>{key}</code> – {label}
                        </span>
                    </label>
                ))}
                <InputError message={errors.scopes} />
            </fieldset>
            <div>
                <Button type="submit" disabled={form.processing}>
                    Anlegen
                </Button>
            </div>
        </form>
    );
}
