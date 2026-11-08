import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useRef } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

declare global {
    interface Window {
        glossaryTurnstile?: (token: string) => void;
    }
}

/** Konto anlegen (#41): Double-Opt-in per E-Mail, Spam-Schutz, Kenntnisnahme der Datenschutzerklärung. */
export default function Register({
    turnstile,
    datenschutzVersion,
}: {
    turnstile: string | null;
    datenschutzVersion: string;
}) {
    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        password: '',
        password_confirmation: '',
        datenschutz: false,
        news: false,
        'cf-turnstile-response': '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const setToken = useRef((token: string) =>
        form.setData('cf-turnstile-response', token),
    );
    useEffect(() => {
        setToken.current = (token: string) =>
            form.setData('cf-turnstile-response', token);
    });

    // Cloudflare Turnstile nur laden, wenn eingerichtet (Datenschutzerklärung nennt Cloudflare)
    useEffect(() => {
        if (!turnstile) {
            return;
        }
        window.glossaryTurnstile = (token: string) => setToken.current(token);
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
        script.async = true;
        document.head.appendChild(script);
        return () => {
            script.remove();
            delete window.glossaryTurnstile;
        };
    }, [turnstile]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/registrieren', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title="Konto anlegen" />
            <div className="mx-auto max-w-md">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Konto anlegen
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Mit einem Konto reichen Sie Vorschläge ein. Nach dem
                    Absenden erhalten Sie einen Bestätigungslink per E-Mail.
                </p>
                {errors.form ? (
                    <Alert variant="destructive" className="mt-4">
                        <AlertDescription>{errors.form}</AlertDescription>
                    </Alert>
                ) : null}
                <form onSubmit={submit} className="mt-6 space-y-4" noValidate>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="first_name">Vorname</Label>
                            <Input
                                id="first_name"
                                autoComplete="given-name"
                                required
                                value={form.data.first_name}
                                onChange={(e) =>
                                    form.setData('first_name', e.target.value)
                                }
                            />
                            <InputError message={errors.first_name} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="last_name">Nachname</Label>
                            <Input
                                id="last_name"
                                autoComplete="family-name"
                                required
                                value={form.data.last_name}
                                onChange={(e) =>
                                    form.setData('last_name', e.target.value)
                                }
                            />
                            <InputError message={errors.last_name} />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="email">E-Mail</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            required
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="password">
                            Passwort (mind. 12 Zeichen)
                        </Label>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="password_confirmation">
                            Passwort wiederholen
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password_confirmation}
                            onChange={(e) =>
                                form.setData(
                                    'password_confirmation',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="datenschutz"
                            className="mt-0.5"
                            checked={form.data.datenschutz}
                            onCheckedChange={(v) =>
                                form.setData('datenschutz', v === true)
                            }
                        />
                        <Label htmlFor="datenschutz" className="leading-snug">
                            <span>
                                Ich habe die{' '}
                                <a
                                    href="/datenschutz"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-link underline underline-offset-4"
                                >
                                    Datenschutzerklärung
                                </a>{' '}
                                (Version {datenschutzVersion}) zur Kenntnis
                                genommen.
                            </span>
                        </Label>
                    </div>
                    <InputError message={errors.datenschutz} />
                    {/* freiwillig, nicht vorausgewählt (#169) */}
                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="news"
                            className="mt-0.5"
                            checked={form.data.news}
                            onCheckedChange={(v) =>
                                form.setData('news', v === true)
                            }
                        />
                        <Label htmlFor="news" className="leading-snug">
                            Gelegentlich News zum Glossary per E-Mail erhalten
                            (keine Werbung, jederzeit abbestellbar) –
                            freiwillig.
                        </Label>
                    </div>
                    {turnstile ? (
                        <div
                            className="cf-turnstile"
                            data-sitekey={turnstile}
                            data-callback="glossaryTurnstile"
                            data-language="de"
                        />
                    ) : null}
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        Konto anlegen
                    </Button>
                </form>
                <p className="mt-6 text-sm">
                    <Link
                        href="/anmelden"
                        className="text-link underline-offset-4 hover:underline"
                    >
                        Schon ein Konto? Anmelden
                    </Link>
                </p>
            </div>
        </>
    );
}
