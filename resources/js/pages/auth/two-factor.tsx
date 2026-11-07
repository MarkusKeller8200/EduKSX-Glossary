import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Anmeldung, Schritt 2: Code aus der Authenticator-App oder Wiederherstellungscode (#11). */
export default function TwoFactorChallenge() {
    const form = useForm({ code: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/anmelden/code', { onFinish: () => form.reset('code') });
    };

    return (
        <>
            <Head title="Code eingeben" />
            <div className="mx-auto max-w-sm">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Code eingeben
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Sechsstelliger Code aus der Authenticator-App – oder einer
                    der Wiederherstellungscodes.
                </p>
                <form onSubmit={submit} className="mt-6 space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="code">Code</Label>
                        <Input
                            id="code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            autoFocus
                            required
                            value={form.data.code}
                            onChange={(e) =>
                                form.setData('code', e.target.value)
                            }
                            aria-invalid={form.errors.code ? true : undefined}
                        />
                        <InputError message={form.errors.code} />
                    </div>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        Anmelden
                    </Button>
                </form>
            </div>
        </>
    );
}
