import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { confirmAction } from '@/components/confirm-dialog';
import ExportMenu from '@/components/export-menu';

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
    passwort_gesetzt: boolean;
    identitaeten: Identitaet[];
    rollen: string[];
};

type Identitaet = {
    id: number;
    anbieter: string;
    art: string;
    seit: string | null;
    zuletzt: string | null;
};

/** Mein Konto (#39): Angaben, Passwort, zweiter Faktor, Konto löschen. */
export default function AccountShow({
    konto,
    einrichtungen,
    microsoft,
}: {
    konto: Konto;
    einrichtungen: Record<string, string>;
    microsoft: boolean;
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
                            ? 'ohne Rolle'
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
            {konto.passwort_gesetzt ? (
                <Passwort />
            ) : (
                <Abschnitt titel="Passwort">
                    <p className="text-sm text-muted-foreground">
                        Sie melden sich über einen Anmeldedienst an. Ein eigenes
                        Passwort setzen Sie über{' '}
                        <Link
                            href="/passwort-vergessen"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            «Passwort vergessen»
                        </Link>
                        .
                    </p>
                </Abschnitt>
            )}
            <Anmeldedienste konto={konto} microsoft={microsoft} />
            <Export />
            <MeineVorschlaege />

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

            <Loeschen passwortGesetzt={konto.passwort_gesetzt} />
        </>
    );
}

/** Link zu den eigenen Vorschlägen (#113), nur mit Recht «propose». */
function MeineVorschlaege() {
    const { auth } = usePage().props;
    if (!auth.can.propose) {
        return null;
    }

    return (
        <Abschnitt titel="Meine Vorschläge">
            <p className="text-sm text-muted-foreground">
                Eingereichte Begriffe und Korrekturen mit ihrem Stand,
                Rückfragen der Redaktion beantworten.
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
                <Button asChild variant="outline">
                    <Link href="/vorschlaege">Meine Vorschläge</Link>
                </Button>
                <Button asChild variant="outline">
                    <Link href="/vorschlaege/neu">Begriff vorschlagen</Link>
                </Button>
            </div>
        </Abschnitt>
    );
}

/** Export veröffentlichter Begriffe (#111), nur mit Recht «export». */
function Export() {
    const { auth } = usePage().props;
    if (!auth.can.export) {
        return null;
    }

    return (
        <Abschnitt titel="Export">
            <p className="text-sm text-muted-foreground">
                Alle veröffentlichten Begriffe mit den öffentlichen Feldern –
                ohne Entwürfe und redaktionelle Notizen. Die Daten stehen unter
                CC BY 4.0 (Quelle «EduKSX-Glossary» angeben).
            </p>
            <div className="mt-3">
                {/* gleiches Menü wie auf der Startseite (#138), hier mit allen Begriffen */}
                <ExportMenu endpoint="/export" allowed />
            </div>
        </Abschnitt>
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

function Anmeldedienste({
    konto,
    microsoft,
}: {
    konto: Konto;
    microsoft: boolean;
}) {
    const form = useForm({ password: '' });
    const errors = form.errors as Record<string, string | undefined>;
    const hatMicrosoft = konto.identitaeten.some(
        (i) => i.anbieter === 'Microsoft',
    );
    const verknuepfen = (e: FormEvent) => {
        e.preventDefault();
        // nach der Passwortbestätigung leitet der Server zu Microsoft weiter
        form.post('/konto/anmeldedienste/microsoft', {
            onFinish: () => form.reset('password'),
        });
    };
    // Lösen mit Passwortfeld statt window.prompt (Klartext, #59)
    const [loesenId, setLoesenId] = useState<number | null>(null);
    const loesenForm = useForm({ password: '' });
    const loesen = (e: FormEvent) => {
        e.preventDefault();
        if (loesenId === null) {
            return;
        }
        loesenForm.delete(`/konto/anmeldedienste/${loesenId}`, {
            preserveScroll: true,
            onSuccess: () => setLoesenId(null),
            onFinish: () => loesenForm.reset('password'),
        });
    };

    if (!microsoft && konto.identitaeten.length === 0) {
        return null;
    }

    return (
        <Abschnitt titel="Anmeldedienste">
            {konto.identitaeten.length > 0 ? (
                <ul className="grid gap-2 text-sm">
                    {konto.identitaeten.map((i) => (
                        <li
                            key={i.id}
                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border p-3"
                        >
                            <span>
                                <span className="font-medium">
                                    {i.anbieter}
                                </span>{' '}
                                ({i.art}) · seit {i.seit ?? '–'} · zuletzt{' '}
                                {i.zuletzt ?? '–'}
                            </span>
                            {loesenId === i.id ? null : (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        loesenForm.clearErrors();
                                        setLoesenId(i.id);
                                    }}
                                >
                                    Lösen
                                </Button>
                            )}
                            {loesenId === i.id ? (
                                <form
                                    onSubmit={loesen}
                                    className="grid w-full gap-2 sm:grid-cols-[1fr_auto_auto] sm:items-end"
                                >
                                    <Feld
                                        id={`unlink_password_${i.id}`}
                                        label={`${i.anbieter} lösen – Passwort bestätigen`}
                                    >
                                        <Input
                                            id={`unlink_password_${i.id}`}
                                            type="password"
                                            autoComplete="current-password"
                                            value={loesenForm.data.password}
                                            onChange={(e) =>
                                                loesenForm.setData(
                                                    'password',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                        <InputError
                                            message={
                                                (
                                                    loesenForm.errors as Record<
                                                        string,
                                                        string | undefined
                                                    >
                                                ).identitaet
                                            }
                                        />
                                    </Feld>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={loesenForm.processing}
                                    >
                                        Verknüpfung lösen
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            setLoesenId(null);
                                            loesenForm.reset('password');
                                        }}
                                    >
                                        Abbrechen
                                    </Button>
                                </form>
                            ) : null}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Noch kein Anmeldedienst verknüpft.
                </p>
            )}
            <InputError className="mt-2" message={errors.identitaet} />
            {microsoft && !hatMicrosoft && konto.passwort_gesetzt ? (
                <form onSubmit={verknuepfen} className="mt-4 grid gap-3">
                    <Feld
                        id="link_password"
                        label="Microsoft verknüpfen – Passwort bestätigen"
                    >
                        <Input
                            id="link_password"
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
                            variant="outline"
                            disabled={form.processing}
                        >
                            Mit Microsoft verknüpfen
                        </Button>
                    </div>
                </form>
            ) : null}
        </Abschnitt>
    );
}

function Loeschen({ passwortGesetzt }: { passwortGesetzt: boolean }) {
    const form = useForm({ password: '' });
    const submit = async (e: FormEvent) => {
        e.preventDefault();
        if (
            await confirmAction(
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
                    label={
                        passwortGesetzt
                            ? 'Passwort bestätigen'
                            : 'Zur Bestätigung «LÖSCHEN» eingeben'
                    }
                    error={form.errors.password}
                >
                    <Input
                        id="delete_password"
                        type={passwortGesetzt ? 'password' : 'text'}
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
