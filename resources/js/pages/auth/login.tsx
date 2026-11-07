import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Anmeldung lokaler Konten (DEC-014); Registrierung und «Passwort vergessen» nur mit Mailversand (#41). */
export default function Login({ registrierung }: { registrierung: boolean }) {
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
                    {registrierung
                        ? 'Mit Konto Vorschläge einreichen und – mit Rechten – das Glossary pflegen.'
                        : 'Für Administration und Redaktion.'}
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
                {registrierung ? (
                    <p className="mt-6 flex flex-wrap justify-between gap-2 text-sm">
                        <Link
                            href="/passwort-vergessen"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            Passwort vergessen?
                        </Link>
                        <Link
                            href="/registrieren"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            Konto anlegen
                        </Link>
                    </p>
                ) : null}
            </div>
        </>
    );
}
