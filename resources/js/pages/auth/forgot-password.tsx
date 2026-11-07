import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Passwort vergessen (#41): Link per E-Mail; die Antwort verrät nicht, ob ein Konto existiert. */
export default function ForgotPassword() {
    const form = useForm({ email: '' });
    const { flash } = usePage().props;
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/passwort-vergessen');
    };

    return (
        <>
            <Head title="Passwort vergessen" />
            <div className="mx-auto max-w-sm">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Passwort vergessen
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Wir senden einen Link, mit dem Sie ein neues Passwort setzen
                    (60 Minuten gültig).
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
                            autoComplete="email"
                            required
                            autoFocus
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                        <InputError message={form.errors.email} />
                    </div>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        Link senden
                    </Button>
                </form>
                <p className="mt-6 text-sm">
                    <Link
                        href="/anmelden"
                        className="text-link underline-offset-4 hover:underline"
                    >
                        Zurück zur Anmeldung
                    </Link>
                </p>
            </div>
        </>
    );
}
