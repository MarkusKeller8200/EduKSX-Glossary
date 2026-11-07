import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Mail = {
    mailer: string;
    host: string | null;
    port: number | null;
    scheme: string | null;
    from: string;
    from_name: string;
    queue: string;
    configured: boolean;
};

/** Systemseite (#40): E-Mail-Versand prüfen. Zugangsdaten stehen nur in der Umgebung des Servers. */
export default function System({
    mail,
    warteschlange,
    version,
}: {
    mail: Mail;
    warteschlange: { offen: number; fehlgeschlagen: number };
    version: string;
}) {
    const { auth } = usePage().props;
    const form = useForm({ an: auth.user?.email ?? '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/system/testmail', { preserveScroll: true });
    };
    const zeilen: [string, string][] = [
        ['Mailer (MAIL_MAILER)', mail.mailer],
        ['Server', mail.host ? `${mail.host}:${mail.port ?? ''}` : '–'],
        ['Verschlüsselung', mail.scheme ?? '–'],
        ['Absender', `${mail.from_name} <${mail.from}>`],
        ['Warteschlange (QUEUE_CONNECTION)', mail.queue],
        [
            'Aufträge',
            `${warteschlange.offen} offen, ${warteschlange.fehlgeschlagen} fehlgeschlagen`,
        ],
        ['Version', version],
    ];

    return (
        <>
            <Head title="System" />
            <h1 className="text-3xl font-semibold tracking-tight">System</h1>

            <section className="mt-6 max-w-2xl">
                <h2 className="flex items-center gap-2 text-lg font-semibold">
                    E-Mail-Versand
                    {mail.configured ? (
                        <Badge>eingerichtet</Badge>
                    ) : (
                        <Badge variant="secondary">nicht eingerichtet</Badge>
                    )}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Der Dienst wird in der Umgebung des Servers eingestellt
                    (Microsoft 365, Google, SMTP des Anbieters oder
                    Versanddienst; Anleitung in DEPLOY.md). Ohne eingerichteten
                    Versand bleibt die Selbstregistrierung ausgeschaltet.
                </p>
                <dl className="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[16rem_1fr]">
                    {zeilen.map(([k, v]) => (
                        <div key={k} className="contents">
                            <dt className="text-muted-foreground">{k}</dt>
                            <dd>{v}</dd>
                        </div>
                    ))}
                </dl>

                <form onSubmit={submit} className="mt-6 grid gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="an">Testmail senden an</Label>
                        <Input
                            id="an"
                            type="email"
                            value={form.data.an}
                            onChange={(e) => form.setData('an', e.target.value)}
                            required
                        />
                        <InputError message={form.errors.an} />
                    </div>
                    <div>
                        <Button type="submit" disabled={form.processing}>
                            Testmail senden
                        </Button>
                    </div>
                </form>
            </section>
        </>
    );
}
