import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Anmeldung, Schritt 2: Code aus der Authenticator-App oder Wiederherstellungscode (#11). */
export default function TwoFactorChallenge({ konto }: { konto: string }) {
    const form = useForm({ code: '' });
    // anderes Konto: Schritt 2 abbrechen (#440)
    const abbrechen = () => router.post('/anmelden/code/abbrechen');
    const zuruecksetzen = () =>
        router.post(
            '/anmelden/code/zuruecksetzen',
            {},
            { preserveScroll: true },
        );
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
                {/* Konto, mit dem man sich gerade anmeldet (#440) */}
                <div className="mt-4 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-lg border border-border bg-muted px-3 py-2 text-sm">
                    <span>
                        <span className="text-muted-foreground">Konto </span>
                        <span className="font-medium break-all">{konto}</span>
                    </span>
                    <Button
                        type="button"
                        variant="link"
                        className="h-auto px-0"
                        onClick={abbrechen}
                    >
                        Mit anderem Konto anmelden
                    </Button>
                </div>
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
                {/* Selbst zurücksetzen per Link (#403); bei erhöhten Rechten nur über die Administration */}
                <div className="mt-6 border-t border-border pt-4 text-sm text-muted-foreground">
                    <p>Kein Zugriff mehr auf die Authenticator-App?</p>
                    <Button
                        type="button"
                        variant="link"
                        className="h-auto px-0"
                        onClick={zuruecksetzen}
                    >
                        Zweiten Faktor zurücksetzen
                    </Button>
                </div>
            </div>
        </>
    );
}
