import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';

type Konto = {
    first_name: string;
    last_name: string;
    email: string;
    community_name: string;
    function: string;
    institution_type: string;
    institution_name: string;
    zweiter_faktor: boolean;
    zweiter_faktor_pflicht: boolean;
    rollen: string[];
};

/** Mein Konto (#39): Angaben, Passwort, zweiter Faktor, Konto löschen. */
export default function AccountShow({
    konto,
    einrichtungen,
}: {
    konto: Konto;
    einrichtungen: Record<string, string>;
}) {
    return (
        <>
            <Head title="Mein Konto" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Mein Konto
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {konto.email} ·{' '}
                        {konto.rollen.length === 0
                            ? 'Community'
                            : konto.rollen.join(', ')}
                    </p>
                </div>
                <Button
                    variant="outline"
                    onClick={() => router.post('/abmelden')}
                >
                    Abmelden
                </Button>
            </div>

            <Angaben konto={konto} einrichtungen={einrichtungen} />
            <Passwort />

            <Abschnitt titel="Zweiter Faktor">
                <p className="text-sm text-muted-foreground">
                    {konto.zweiter_faktor
                        ? 'Eingerichtet.'
                        : konto.zweiter_faktor_pflicht
                          ? 'Für Ihre Rechte nötig – bitte einrichten.'
                          : 'Freiwillig – schützt das Konto zusätzlich mit einem Code aus einer Authenticator-App.'}
                </p>
                <div className="mt-3">
                    <Button asChild variant="outline">
                        <Link href="/konto/zwei-faktor">
                            {konto.zweiter_faktor ? 'Verwalten' : 'Einrichten'}
                        </Link>
                    </Button>
                </div>
            </Abschnitt>

            <Loeschen />
        </>
    );
}

function Abschnitt({
    titel,
    children,
}: {
    titel: string;
    children: ReactNode;
}) {
    return (
        <section className="mt-8 max-w-2xl border-t border-border pt-6">
            <h2 className="text-lg font-semibold">{titel}</h2>
            <div className="mt-3">{children}</div>
        </section>
    );
}

function Feld({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function Angaben({
    konto,
    einrichtungen,
}: {
    konto: Konto;
    einrichtungen: Record<string, string>;
}) {
    const form = useForm({
        first_name: konto.first_name,
        last_name: konto.last_name,
        community_name: konto.community_name,
        function: konto.function,
        institution_type: konto.institution_type,
        institution_name: konto.institution_name,
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/konto', { preserveScroll: true });
    };

    return (
        <Abschnitt titel="Angaben">
            <form onSubmit={submit} className="grid gap-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Feld
                        id="first_name"
                        label="Vorname *"
                        error={form.errors.first_name}
                    >
                        <Input
                            id="first_name"
                            autoComplete="given-name"
                            value={form.data.first_name}
                            onChange={(e) =>
                                form.setData('first_name', e.target.value)
                            }
                            required
                        />
                    </Feld>
                    <Feld
                        id="last_name"
                        label="Nachname *"
                        error={form.errors.last_name}
                    >
                        <Input
                            id="last_name"
                            autoComplete="family-name"
                            value={form.data.last_name}
                            onChange={(e) =>
                                form.setData('last_name', e.target.value)
                            }
                            required
                        />
                    </Feld>
                </div>
                <Feld
                    id="community_name"
                    label="Community-Name (freiwillig, öffentlich bei Vorschlägen)"
                    error={form.errors.community_name}
                >
                    <Input
                        id="community_name"
                        value={form.data.community_name}
                        onChange={(e) =>
                            form.setData('community_name', e.target.value)
                        }
                    />
                </Feld>
                <Feld
                    id="function"
                    label="Funktion"
                    error={form.errors.function}
                >
                    <Input
                        id="function"
                        placeholder="z. B. Schulleitung"
                        value={form.data.function}
                        onChange={(e) =>
                            form.setData('function', e.target.value)
                        }
                    />
                </Feld>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Feld
                        id="institution_type"
                        label="Öffentliche Einrichtung"
                        error={form.errors.institution_type}
                    >
                        <NativeSelect
                            id="institution_type"
                            value={form.data.institution_type}
                            onChange={(e) =>
                                form.setData('institution_type', e.target.value)
                            }
                        >
                            <option value="">–</option>
                            {Object.entries(einrichtungen).map(
                                ([wert, text]) => (
                                    <option key={wert} value={wert}>
                                        {text}
                                    </option>
                                ),
                            )}
                        </NativeSelect>
                    </Feld>
                    <Feld
                        id="institution_name"
                        label="Gemeinde / Schule"
                        error={form.errors.institution_name}
                    >
                        <Input
                            id="institution_name"
                            value={form.data.institution_name}
                            onChange={(e) =>
                                form.setData('institution_name', e.target.value)
                            }
                        />
                    </Feld>
                </div>
                <p className="text-sm text-muted-foreground">
                    Nur Moderation und Administration sehen Name, E-Mail,
                    Funktion und Einrichtung. Die E-Mail-Adresse lässt sich
                    ändern, sobald die Bestätigung per Mail eingerichtet ist.
                </p>
                <div>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Angaben speichern
                    </Button>
                </div>
            </form>
        </Abschnitt>
    );
}

function Passwort() {
    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/konto/passwort', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Abschnitt titel="Passwort">
            <form onSubmit={submit} className="grid gap-4">
                <Feld
                    id="current_password"
                    label="Bisheriges Passwort"
                    error={form.errors.current_password}
                >
                    <Input
                        id="current_password"
                        type="password"
                        autoComplete="current-password"
                        value={form.data.current_password}
                        onChange={(e) =>
                            form.setData('current_password', e.target.value)
                        }
                        required
                    />
                </Feld>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Feld
                        id="password"
                        label="Neues Passwort (mind. 12 Zeichen)"
                        error={form.errors.password}
                    >
                        <Input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                            required
                        />
                    </Feld>
                    <Feld id="password_confirmation" label="Wiederholen">
                        <Input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(e) =>
                                form.setData(
                                    'password_confirmation',
                                    e.target.value,
                                )
                            }
                            required
                        />
                    </Feld>
                </div>
                <div>
                    <Button type="submit" disabled={form.processing}>
                        Passwort ändern
                    </Button>
                </div>
            </form>
        </Abschnitt>
    );
}

function Loeschen() {
    const form = useForm({ password: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (
            window.confirm(
                'Konto endgültig löschen? Die Anmeldung ist danach nicht mehr möglich.',
            )
        ) {
            form.post('/konto/loeschen', { preserveScroll: true });
        }
    };

    return (
        <Abschnitt titel="Konto löschen">
            <form onSubmit={submit} className="grid gap-4">
                <p className="text-sm text-muted-foreground">
                    Löscht Ihre Angaben und beendet alle Sitzungen. Einträge im
                    Sicherheitsprotokoll bleiben nach dem Löschkonzept erhalten.
                </p>
                <Feld
                    id="delete_password"
                    label="Passwort bestätigen"
                    error={form.errors.password}
                >
                    <Input
                        id="delete_password"
                        type="password"
                        autoComplete="current-password"
                        value={form.data.password}
                        onChange={(e) =>
                            form.setData('password', e.target.value)
                        }
                        required
                    />
                </Feld>
                <div>
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={form.processing}
                    >
                        Konto löschen
                    </Button>
                </div>
            </form>
        </Abschnitt>
    );
}
