import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Rolle = {
    id: string;
    key: string;
    name: string;
    beschreibung: string | null;
    system: boolean;
    eigene: boolean;
    rechte: string[];
    konten: number;
};

type Recht = {
    key: string;
    label: string;
    klasse: 'normal' | 'privileged' | 'system';
    eigenes: boolean;
};

const KLASSE: Record<Recht['klasse'], string> = {
    normal: 'normal',
    privileged: 'privilegiert',
    system: 'systemkritisch',
};

/** Rollen und Rechte (#38): Matrix mit Schutzklassen, eigene Rollen anlegen. */
export default function Roles({
    rollen,
    rechte,
    developer,
}: {
    rollen: Rolle[];
    rechte: Recht[];
    developer: boolean;
}) {
    return (
        <>
            <Head title="Rollen" />
            <p className="text-sm">
                <Link
                    href="/admin/benutzer"
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← Benutzer
                </Link>
            </p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                Rollen und Rechte
            </h1>
            <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                Rechte je Rolle einstellen. Privilegierte Rechte nur innerhalb
                der eigenen Befugnis, einer eigenen Rolle keine Rechte
                hinzufügen; systemkritische Rechte hat nur die Rolle Developer.
                Wer ein Recht ausser «Vorschläge einreichen» hat, braucht den
                zweiten Faktor.
            </p>

            <div className="mt-6 grid gap-4 lg:grid-cols-2">
                {rollen.map((r) => (
                    <RolleKarte
                        key={r.id}
                        rolle={r}
                        rechte={rechte}
                        developer={developer}
                    />
                ))}
            </div>

            <NeueRolle />
        </>
    );
}

function RolleKarte({
    rolle,
    rechte,
    developer,
}: {
    rolle: Rolle;
    rechte: Recht[];
    developer: boolean;
}) {
    const form = useForm<{ rechte: string[] }>({ rechte: rolle.rechte });
    const errors = form.errors as Record<string, string | undefined>;
    const gesperrt = (r: Recht) =>
        rolle.system ||
        r.klasse === 'system' ||
        (!developer &&
            (!r.eigenes || (rolle.eigene && !rolle.rechte.includes(r.key))));
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/rollen/${rolle.id}`, { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                    {rolle.name}
                    {rolle.system ? (
                        <Badge variant="secondary">Systemrolle</Badge>
                    ) : null}
                    {rolle.eigene ? (
                        <Badge variant="outline">eigene Rolle</Badge>
                    ) : null}
                    <span className="text-sm font-normal text-muted-foreground">
                        {rolle.konten === 1
                            ? '1 Konto'
                            : `${rolle.konten} Konten`}
                    </span>
                </CardTitle>
                {rolle.beschreibung ? (
                    <p className="text-sm text-muted-foreground">
                        {rolle.beschreibung}
                    </p>
                ) : null}
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-2">
                    {rechte.map((r) => (
                        <label
                            key={r.key}
                            className="flex items-center gap-3 text-sm"
                        >
                            <Checkbox
                                checked={form.data.rechte.includes(r.key)}
                                disabled={gesperrt(r)}
                                onCheckedChange={(v) =>
                                    form.setData(
                                        'rechte',
                                        v === true
                                            ? [...form.data.rechte, r.key]
                                            : form.data.rechte.filter(
                                                  (k) => k !== r.key,
                                              ),
                                    )
                                }
                            />
                            <span>{r.label}</span>
                            <span className="text-xs text-muted-foreground">
                                {KLASSE[r.klasse]}
                            </span>
                        </label>
                    ))}
                    <InputError message={errors.rechte} />
                    {rolle.system ? (
                        <p className="text-sm text-muted-foreground">
                            Developer hat immer alle Rechte; nicht einstellbar.
                        </p>
                    ) : (
                        <div className="mt-2">
                            <Button
                                type="submit"
                                size="sm"
                                disabled={form.processing || !form.isDirty}
                            >
                                Rechte speichern
                            </Button>
                        </div>
                    )}
                </form>
            </CardContent>
        </Card>
    );
}

function NeueRolle() {
    const form = useForm({ name: '', beschreibung: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/rollen', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="mt-8 grid max-w-xl gap-4 rounded-lg border border-border p-4"
        >
            <h2 className="text-lg font-semibold">Neue Rolle</h2>
            <div className="grid gap-2">
                <Label htmlFor="rolle-name">Name</Label>
                <Input
                    id="rolle-name"
                    placeholder="z. B. Redaktion Schule"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    required
                />
                <InputError message={form.errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="rolle-beschreibung">Beschreibung</Label>
                <Input
                    id="rolle-beschreibung"
                    value={form.data.beschreibung}
                    onChange={(e) =>
                        form.setData('beschreibung', e.target.value)
                    }
                />
            </div>
            <div>
                <Button type="submit" disabled={form.processing}>
                    Rolle anlegen
                </Button>
            </div>
        </form>
    );
}
