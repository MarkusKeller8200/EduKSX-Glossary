import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import QrCode from '@/components/qr-code';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Zweiter Faktor einrichten bzw. verwalten (#11). Pflicht für privilegierte Rechte, sonst freiwillig (#37, #39). */
export default function TwoFactorSetup({
    eingerichtet,
    schluessel,
    uri,
    codes,
    verbleibend,
}: {
    eingerichtet: boolean;
    schluessel: string | null;
    uri: string | null;
    codes: string[] | null;
    verbleibend: number;
}) {
    const form = useForm({ code: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/konto/zwei-faktor', { onFinish: () => form.reset('code') });
    };

    return (
        <>
            <Head title="Zweiter Faktor" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Zweiter Faktor
            </h1>

            {codes && codes.length > 0 ? (
                <Alert className="mt-6 max-w-2xl">
                    <AlertTitle>Wiederherstellungscodes</AlertTitle>
                    <AlertDescription>
                        <p>
                            Jetzt ausdrucken oder im Passwort-Manager speichern.
                            Jeder Code gilt einmal, falls das Handy fehlt. Sie
                            werden nur dieses eine Mal angezeigt.
                        </p>
                        <ul className="mt-3 grid grid-cols-2 gap-1 font-mono text-sm">
                            {codes.map((c) => (
                                <li key={c}>{c}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            ) : null}

            {eingerichtet ? (
                <div className="mt-6 max-w-2xl space-y-4 text-sm">
                    <p>
                        Der zweite Faktor ist eingerichtet. Noch{' '}
                        <strong>{verbleibend}</strong> Wiederherstellungscodes
                        sind gültig.
                    </p>
                    <Button
                        variant="outline"
                        onClick={() => {
                            if (
                                window.confirm(
                                    'Neue Wiederherstellungscodes erzeugen? Die bisherigen gelten danach nicht mehr.',
                                )
                            ) {
                                router.post('/konto/zwei-faktor/codes');
                            }
                        }}
                    >
                        Neue Wiederherstellungscodes
                    </Button>
                    <p className="text-muted-foreground">
                        Handy verloren? Auf dem Server zurücksetzen:{' '}
                        <code>
                            php artisan glossary:admin E-MAIL --reset-2fa
                        </code>
                    </p>
                </div>
            ) : (
                <div className="mt-6 max-w-2xl space-y-6 text-sm">
                    <p>
                        Die Administration verlangt einen zweiten Faktor. Mit
                        einer Authenticator-App (z. B. Microsoft Authenticator,
                        Google Authenticator, 1Password, Bitwarden) einrichten:
                    </p>
                    <ol className="list-decimal space-y-3 pl-6">
                        {uri ? (
                            <li>
                                In der App ein neues Konto hinzufügen und diesen
                                QR-Code scannen:
                                <div className="mt-2">
                                    <QrCode
                                        value={uri}
                                        label="QR-Code für die Authenticator-App"
                                    />
                                </div>
                            </li>
                        ) : null}
                        <li>
                            {uri
                                ? 'Oder den Schlüssel von Hand eingeben (Typ: zeitbasiert):'
                                : 'In der App ein neues Konto hinzufügen und diesen Schlüssel eingeben (Typ: zeitbasiert):'}
                            <p
                                className="mt-2 rounded-md bg-muted px-3 py-2 font-mono text-base tracking-wider"
                                translate="no"
                            >
                                {schluessel}
                            </p>
                            {uri ? (
                                <p className="mt-2">
                                    Auf dem Handy geht auch dieser Link:{' '}
                                    <a
                                        href={uri}
                                        className="text-link underline underline-offset-4"
                                    >
                                        In Authenticator-App öffnen
                                    </a>
                                </p>
                            ) : null}
                        </li>
                        <li>
                            Den sechsstelligen Code aus der App hier eingeben:
                            <form
                                onSubmit={submit}
                                className="mt-2 flex max-w-sm items-start gap-2"
                            >
                                <div className="flex-1 space-y-1">
                                    <Label htmlFor="code" className="sr-only">
                                        Code
                                    </Label>
                                    <Input
                                        id="code"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        required
                                        value={form.data.code}
                                        onChange={(e) =>
                                            form.setData('code', e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.code} />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Bestätigen
                                </Button>
                            </form>
                        </li>
                    </ol>
                </div>
            )}
        </>
    );
}
