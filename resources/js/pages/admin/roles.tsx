import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { palette } from '@/lib/colors';
import { cn } from '@/lib/utils';

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

/** Farbe der Schutzklasse (#115): normal grün, privilegiert orange, systemkritisch rot. */
const KLASSE_FARBE: Record<Recht['klasse'], string> = {
    normal: palette.emerald.badge,
    privileged: palette.amber.badge,
    system: palette.rose.badge,
};

/** Voreingestellte Rollen in aufsteigender Reihenfolge (#115); eigene Rollen stehen nur in der Matrix. */
const HIERARCHIE = [
    'guest',
    'user',
    'org-admin',
    'moderator',
    'admin',
    'developer',
];

/** Rollen und Rechte (#38): Hierarchie (#115), Matrix mit Schutzklassen, eigene Rollen anlegen. */
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
            <p className="mt-2 text-sm text-muted-foreground">
                Rechte je Rolle einstellen. Privilegierte Rechte nur innerhalb
                der eigenen Befugnis, einer eigenen Rolle keine Rechte
                hinzufügen; systemkritische Rechte hat nur die Rolle Developer.
                Wer ein Recht ausser den Grundrechten (Vorschläge einreichen,
                Exportieren, persönliche Darstellung) hat, braucht den zweiten
                Faktor.
            </p>

            <Hierarchie rollen={rollen} rechte={rechte} />

            <h2 className="mt-8 text-lg font-semibold">Rechte je Rolle</h2>

            <div className="mt-3 grid gap-4 lg:grid-cols-2">
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

/**
 * Rollen als Stufen mit den Rechten, die jede Stufe gegenüber der vorherigen dazubekommt (#115). Dient dem
 * Verständnis; geprüft wird weiterhin je Recht. Fehlt einer Stufe ein Recht der vorherigen, steht es als «ohne».
 */
function Hierarchie({ rollen, rechte }: { rollen: Rolle[]; rechte: Recht[] }) {
    const stufen = HIERARCHIE.map((key) =>
        rollen.find((r) => r.key === key),
    ).filter((r): r is Rolle => r !== undefined);
    const recht = (key: string) => rechte.find((r) => r.key === key);

    return (
        <section className="mt-6" aria-labelledby="hierarchie">
            <h2 id="hierarchie" className="text-lg font-semibold">
                Hierarchie
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                Jede Stufe mit den Rechten, die sie dazubekommt. Schutzklassen:{' '}
                {(['normal', 'privileged', 'system'] as const).map((k, i) => (
                    <span key={k}>
                        {i > 0 ? ', ' : null}
                        <Badge
                            variant="outline"
                            className={cn(KLASSE_FARBE[k])}
                        >
                            {KLASSE[k]}
                        </Badge>
                    </span>
                ))}
                . Geprüft wird immer das einzelne Recht.
            </p>
            <ol className="mt-3 grid gap-2">
                {stufen.map((rolle, i) => {
                    const vorher = i > 0 ? stufen[i - 1].rechte : [];
                    const dazu = rechte.filter(
                        (r) =>
                            rolle.rechte.includes(r.key) &&
                            !vorher.includes(r.key),
                    );
                    const ohne = vorher.filter(
                        (k) => !rolle.rechte.includes(k),
                    );

                    return (
                        <li
                            key={rolle.id}
                            className="grid gap-2 rounded-lg border border-border p-3 sm:grid-cols-[12rem_1fr]"
                        >
                            <div className="font-medium">
                                <span className="text-muted-foreground">
                                    {i + 1}.
                                </span>{' '}
                                {rolle.name}
                            </div>
                            <div className="flex flex-wrap items-center gap-1 text-sm">
                                {i === 0 && dazu.length === 0 ? (
                                    <span className="text-muted-foreground">
                                        nur lesen
                                    </span>
                                ) : null}
                                {i > 0 && dazu.length === 0 ? (
                                    <span className="text-muted-foreground">
                                        keine weiteren Rechte
                                    </span>
                                ) : null}
                                {dazu.map((r) => (
                                    <Badge
                                        key={r.key}
                                        variant="outline"
                                        className={cn(KLASSE_FARBE[r.klasse])}
                                        title={KLASSE[r.klasse]}
                                    >
                                        + {r.label}
                                    </Badge>
                                ))}
                                {ohne.map((k) => (
                                    <Badge
                                        key={k}
                                        variant="outline"
                                        className="text-muted-foreground line-through"
                                        title="hat diese Stufe nicht"
                                    >
                                        ohne {recht(k)?.label ?? k}
                                    </Badge>
                                ))}
                            </div>
                        </li>
                    );
                })}
            </ol>
        </section>
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
