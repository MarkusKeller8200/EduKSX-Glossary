import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';

export type MailEinstellung = {
    quelle: 'umgebung' | 'administration' | 'vorgabe';
    mailer: string;
    host: string;
    port: number | null;
    scheme: string;
    username: string;
    passwort_gesetzt: boolean;
    passwort_unlesbar: boolean;
    from_address: string;
    from_name: string;
    geprueft_am: string | null;
};

/** Vorlagen der gängigen Anbieter (DEPLOY.md, Abschnitt E-Mail-Versand). */
const VORLAGEN: Record<string, { host: string; port: number; scheme: string }> =
    {
        'Microsoft 365': {
            host: 'smtp.office365.com',
            port: 587,
            scheme: 'smtp',
        },
        Google: { host: 'smtp.gmail.com', port: 587, scheme: 'smtp' },
        Infomaniak: { host: 'mail.infomaniak.com', port: 465, scheme: 'smtps' },
        Hostinger: { host: 'smtp.hostinger.com', port: 465, scheme: 'smtps' },
    };

/**
 * Mail-Versand in der Administration einrichten (#219): Speichern sendet sofort eine Testmail; erst danach gilt der
 * Versand als eingerichtet. Das Passwort wird nie angezeigt. Gibt der Server den Versand über die Umgebung vor, ist
 * das Formular gesperrt.
 */
export default function MailSettingsForm({
    einstellung: e,
    an,
}: {
    einstellung: MailEinstellung;
    an: string;
}) {
    const gesperrt = e.quelle === 'umgebung';
    const form = useForm({
        mailer: e.mailer || 'smtp',
        host: e.host,
        port: e.port ?? 587,
        scheme: e.scheme,
        username: e.username,
        password: '',
        passwort_loeschen: false,
        from_address: e.from_address,
        from_name: e.from_name,
        an,
    });
    const smtp = form.data.mailer === 'smtp';
    const submit = (ev: FormEvent) => {
        ev.preventDefault();
        form.post('/admin/system/mail', {
            preserveScroll: true,
            onSuccess: () => form.reset('password', 'passwort_loeschen'),
        });
    };
    const vorlage = (name: string) => {
        const v = VORLAGEN[name];
        if (v) {
            form.setData((d) => ({ ...d, ...v, mailer: 'smtp' }));
        }
    };

    return (
        <section className="mt-8 max-w-2xl">
            <h2 className="flex items-center gap-2 text-lg font-semibold">
                Versand einrichten
                {e.quelle === 'umgebung' ? (
                    <Badge variant="secondary">über die Umgebung</Badge>
                ) : e.quelle === 'administration' ? (
                    e.geprueft_am ? (
                        <Badge>geprüft</Badge>
                    ) : (
                        <Badge variant="outline">Testmail fehlt</Badge>
                    )
                ) : null}
            </h2>
            {gesperrt ? (
                <p className="mt-1 text-sm text-muted-foreground">
                    Der Versand ist auf dem Server über die Umgebung eingestellt
                    (MAIL_MAILER). Diese Einstellung hat Vorrang; ändern lässt
                    sie sich nur dort. Wer den Versand hier einrichten will,
                    entfernt MAIL_MAILER aus der Umgebung des Servers.
                </p>
            ) : (
                <p className="mt-1 text-sm text-muted-foreground">
                    «Speichern und testen» sendet sofort eine Testmail; erst
                    danach gilt der Versand als eingerichtet und die
                    Registrierung wird möglich. Das Passwort wird verschlüsselt
                    gespeichert und nie angezeigt.
                </p>
            )}

            <form onSubmit={submit} className="mt-4 grid gap-4">
                <fieldset disabled={gesperrt} className="grid gap-4">
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="mailer">Art</Label>
                            <NativeSelect
                                id="mailer"
                                value={form.data.mailer}
                                onChange={(ev) =>
                                    form.setData('mailer', ev.target.value)
                                }
                            >
                                <option value="smtp">SMTP</option>
                                <option value="log">
                                    nur Protokoll (kein Versand)
                                </option>
                            </NativeSelect>
                            <InputError message={form.errors.mailer} />
                        </div>
                        {smtp ? (
                            <div className="grid gap-2">
                                <Label htmlFor="vorlage">Vorlage</Label>
                                <NativeSelect
                                    id="vorlage"
                                    value=""
                                    onChange={(ev) => vorlage(ev.target.value)}
                                >
                                    <option value="">Anbieter wählen …</option>
                                    {Object.keys(VORLAGEN).map((n) => (
                                        <option key={n} value={n}>
                                            {n}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                        ) : null}
                    </div>

                    {smtp ? (
                        <>
                            <div className="grid gap-2 sm:grid-cols-[1fr_7rem_10rem]">
                                <div className="grid gap-2">
                                    <Label htmlFor="host">Server</Label>
                                    <Input
                                        id="host"
                                        value={form.data.host}
                                        onChange={(ev) =>
                                            form.setData(
                                                'host',
                                                ev.target.value,
                                            )
                                        }
                                        placeholder="smtp.example.ch"
                                    />
                                    <InputError message={form.errors.host} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="port">Port</Label>
                                    <Input
                                        id="port"
                                        type="number"
                                        value={form.data.port}
                                        onChange={(ev) =>
                                            form.setData(
                                                'port',
                                                Number(ev.target.value),
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.port} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="scheme">
                                        Verschlüsselung
                                    </Label>
                                    <NativeSelect
                                        id="scheme"
                                        value={form.data.scheme}
                                        onChange={(ev) =>
                                            form.setData(
                                                'scheme',
                                                ev.target.value,
                                            )
                                        }
                                    >
                                        <option value="">automatisch</option>
                                        <option value="smtp">
                                            STARTTLS (587)
                                        </option>
                                        <option value="smtps">TLS (465)</option>
                                    </NativeSelect>
                                </div>
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="username">Benutzer</Label>
                                    <Input
                                        id="username"
                                        value={form.data.username}
                                        autoComplete="off"
                                        onChange={(ev) =>
                                            form.setData(
                                                'username',
                                                ev.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Passwort</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.data.password}
                                        placeholder={
                                            e.passwort_gesetzt
                                                ? 'gesetzt – leer lassen zum Behalten'
                                                : ''
                                        }
                                        onChange={(ev) =>
                                            form.setData(
                                                'password',
                                                ev.target.value,
                                            )
                                        }
                                    />
                                    {e.passwort_unlesbar ? (
                                        <p className="text-sm text-destructive">
                                            Das gespeicherte Passwort ist mit
                                            einem anderen Schlüssel
                                            verschlüsselt – bitte neu eingeben.
                                        </p>
                                    ) : null}
                                    {e.passwort_gesetzt ? (
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={
                                                    form.data.passwort_loeschen
                                                }
                                                onCheckedChange={(c) =>
                                                    form.setData(
                                                        'passwort_loeschen',
                                                        c === true,
                                                    )
                                                }
                                            />
                                            Gespeichertes Passwort löschen
                                        </label>
                                    ) : null}
                                    <InputError
                                        message={form.errors.password}
                                    />
                                </div>
                            </div>
                        </>
                    ) : null}

                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="from_address">Absender</Label>
                            <Input
                                id="from_address"
                                type="email"
                                value={form.data.from_address}
                                onChange={(ev) =>
                                    form.setData(
                                        'from_address',
                                        ev.target.value,
                                    )
                                }
                                placeholder="noreply@example.ch"
                                required
                            />
                            <InputError message={form.errors.from_address} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="from_name">Absendername</Label>
                            <Input
                                id="from_name"
                                value={form.data.from_name}
                                onChange={(ev) =>
                                    form.setData('from_name', ev.target.value)
                                }
                                placeholder="EduKSX-Glossary"
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="mail-an">Testmail an</Label>
                        <Input
                            id="mail-an"
                            type="email"
                            value={form.data.an}
                            onChange={(ev) =>
                                form.setData('an', ev.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.an} />
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {smtp ? 'Speichern und testen' : 'Speichern'}
                        </Button>
                        {e.quelle === 'administration' ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        '/admin/system/mail/entfernen',
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Einstellung entfernen
                            </Button>
                        ) : null}
                    </div>
                </fieldset>
            </form>
        </section>
    );
}
