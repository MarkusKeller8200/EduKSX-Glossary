import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Anmeldung lokaler Konten (DEC-014); Registrierung und «Passwort vergessen» nur mit Mailversand (#41). */
export default function Login({
    registrierung,
    microsoft,
}: {
    registrierung: boolean;
    microsoft: boolean;
}) {
    const form = useForm({ email: '', password: '', remember: false });
    const { flash } = usePage().props;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/anmelden', { onFinish: () => form.reset('password') });
    };

    return (
        <>
            <Head title="Anmelden" />
            <div className="mx-auto max-w-sm">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Anmelden
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Lesen geht ohne Konto. Für weitere Funktionen melden Sie
                    sich an.
                </p>
                {flash.status ? (
                    <Alert className="mt-4" role="status">
                        <AlertDescription>{flash.status}</AlertDescription>
                    </Alert>
                ) : null}
                <form onSubmit={submit} className="mt-6 space-y-4" noValidate>
                    <div className="space-y-2">
                        <Label htmlFor="email">E-Mail</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="username"
                            required
                            autoFocus
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            aria-invalid={form.errors.email ? true : undefined}
                        />
                        <InputError message={form.errors.email} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="password">Passwort</Label>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="current-password"
                            required
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                        />
                        <InputError message={form.errors.password} />
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="remember"
                            checked={form.data.remember}
                            onCheckedChange={(v) =>
                                form.setData('remember', v === true)
                            }
                        />
                        <Label htmlFor="remember">Angemeldet bleiben</Label>
                    </div>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        Anmelden
                    </Button>
                </form>
                {microsoft ? (
                    <div className="mt-4">
                        <div className="my-4 flex items-center gap-3 text-xs text-muted-foreground">
                            <span className="h-px flex-1 bg-border" />
                            oder
                            <span className="h-px flex-1 bg-border" />
                        </div>
                        <Button asChild variant="outline" className="w-full">
                            {/* volle Seitennavigation: Weiterleitung zu Microsoft */}
                            <a href="/anmelden/microsoft">
                                Mit Microsoft anmelden
                            </a>
                        </Button>
                    </div>
                ) : null}
                {registrierung ? (
                    <>
                        <p className="mt-6 text-sm">
                            <Link
                                href="/passwort-vergessen"
                                className="text-link underline-offset-4 hover:underline"
                            >
                                Passwort vergessen?
                            </Link>
                        </p>
                        <p className="mt-4 rounded-lg border border-border p-3 text-sm">
                            Noch kein Konto?{' '}
                            <Link
                                href="/registrieren"
                                className="font-medium text-link underline underline-offset-4"
                            >
                                Hier ein Konto erstellen
                            </Link>
                        </p>
                    </>
                ) : null}

                {/* Was man angemeldet tun kann (#168) */}
                <section className="mt-8 border-t border-border pt-6 text-sm">
                    <h2 className="font-semibold">
                        Mit einem Konto (Benutzer)
                    </h2>
                    <ul className="mt-2 list-disc space-y-1 pl-5 text-muted-foreground">
                        <li>
                            Begriffe und Korrekturen vorschlagen, Stand
                            verfolgen
                        </li>
                        <li>Exportieren: Excel, CSV, PDF und Markdown</li>
                        <li>
                            Organisation gründen oder beitreten und die
                            Darstellung der Organisation sehen
                        </li>
                        <li>
                            Eigene Texte zu Begriffen («Meine Darstellung») als
                            Mitglied einer Organisation
                        </li>
                        <li>Freiwillig: News zum Glossary (keine Werbung)</li>
                    </ul>
                    <p className="mt-3 text-muted-foreground">
                        Für Organisations-Admins, Moderatoren, Admins und
                        Entwickler ist der zweite Faktor (App mit Einmal-Codes)
                        Pflicht; für Benutzer freiwillig.{' '}
                        <Link
                            href="/hilfe"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            Hilfe
                        </Link>
                    </p>
                </section>
            </div>
        </>
    );
}
