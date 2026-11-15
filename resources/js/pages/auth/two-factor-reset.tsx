import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Zweiten Faktor zurücksetzen (#403): Seite des Links aus der Mail, bestätigen mit E-Mail und Passwort. */
export default function TwoFactorReset({ aktion }: { aktion: string }) {
    const form = useForm({ email: '', password: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(aktion, { onFinish: () => form.reset('password') });
    };

    return (
        <>
            <Head title="Zweiten Faktor zurücksetzen" />
            <div className="mx-auto max-w-sm">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Zweiten Faktor zurücksetzen
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Zur Bestätigung E-Mail-Adresse und Passwort Ihres Kontos
                    eingeben. Danach ist der zweite Faktor entfernt; nach der
                    nächsten Anmeldung können Sie ihn wieder einrichten.
                </p>
                <form onSubmit={submit} className="mt-6 space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="email">E-Mail</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            autoFocus
                            required
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
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        Zweiten Faktor entfernen
                    </Button>
                </form>
            </div>
        </>
    );
}
