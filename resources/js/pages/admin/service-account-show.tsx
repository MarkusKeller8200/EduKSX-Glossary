import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Konto = {
    id: string;
    name: string;
    zweck: string;
    verantwortlich: string | null;
    status: string;
    scopes: string[];
    angelegt: string | null;
};

type Token = {
    id: string;
    name: string;
    praefix: string;
    bis: string;
    zuletzt: string | null;
    widerrufen: string | null;
    gueltig: boolean;
};

type Eintrag = {
    am: string;
    aktion: string;
    von: string | null;
    ergebnis: string;
    details: Record<string, unknown> | null;
};

const datum = (iso: string) =>
    new Date(iso).toLocaleString('de-CH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

/** Servicekonto mit Token und Protokoll (#47). */
export default function ServiceAccountShow({
    konto,
    token,
    neuesToken,
    protokoll,
    scopeNamen,
    maxTage,
}: {
    konto: Konto;
    token: Token[];
    neuesToken: string | null;
    protokoll: Eintrag[];
    scopeNamen: Record<string, string>;
    maxTage: number;
}) {
    const aktiv = konto.status === 'active';

    return (
        <>
            <Head title={konto.name} />
            <p className="text-sm">
                <Link
                    href="/admin/servicekonten"
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← Servicekonten
                </Link>
            </p>
            <div className="mt-2 flex flex-wrap items-end justify-between gap-4">
                <h1 className="text-3xl font-semibold tracking-tight">
                    {konto.name}{' '}
                    {aktiv ? null : <Badge variant="outline">gesperrt</Badge>}
                </h1>
                <Button
                    variant="outline"
                    onClick={() =>
                        router.post(
                            `/admin/servicekonten/${konto.id}/status`,
                            { aktiv: !aktiv },
                            { preserveScroll: true },
                        )
                    }
                >
                    {aktiv ? 'Sperren' : 'Entsperren'}
                </Button>
            </div>
            <dl className="mt-4 grid max-w-2xl gap-x-6 gap-y-1 text-sm sm:grid-cols-[10rem_1fr]">
                <dt className="text-muted-foreground">Zweck</dt>
                <dd>{konto.zweck}</dd>
                <dt className="text-muted-foreground">Verantwortlich</dt>
                <dd>{konto.verantwortlich ?? '–'}</dd>
                <dt className="text-muted-foreground">Scopes</dt>
                <dd>
                    {konto.scopes.length
                        ? konto.scopes.map((s) => scopeNamen[s] ?? s).join('; ')
                        : 'keine (nur Selbstauskunft)'}
                </dd>
                <dt className="text-muted-foreground">Angelegt</dt>
                <dd>{konto.angelegt ?? '–'}</dd>
            </dl>

            {neuesToken ? <NeuesToken wert={neuesToken} /> : null}

            <h2 className="mt-8 text-lg font-semibold">Token</h2>
            {token.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Noch kein Token.
                </p>
            ) : (
                <ul className="mt-2 grid max-w-2xl gap-2">
                    {token.map((t) => (
                        <li
                            key={t.id}
                            className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border p-3 text-sm"
                        >
                            <div>
                                <span className="font-medium">{t.name}</span>{' '}
                                <code className="text-muted-foreground">
                                    {t.praefix}…
                                </code>
                                <span className="block text-muted-foreground">
                                    {t.widerrufen
                                        ? `widerrufen am ${t.widerrufen}`
                                        : t.gueltig
                                          ? `gültig bis ${t.bis}`
                                          : `abgelaufen am ${t.bis}`}
                                    {t.zuletzt
                                        ? ` · zuletzt genutzt ${datum(t.zuletzt)}`
                                        : ' · noch nicht genutzt'}
                                </span>
                            </div>
                            {t.gueltig ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.delete(
                                            `/admin/servicekonten/token/${t.id}`,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Widerrufen
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
            {aktiv ? <NeuesTokenForm id={konto.id} maxTage={maxTage} /> : null}

            <h2 className="mt-8 text-lg font-semibold">Protokoll</h2>
            <ul className="mt-2 grid max-w-2xl gap-1 text-sm">
                {protokoll.map((e, i) => (
                    <li key={i}>
                        <span className="text-muted-foreground">
                            {datum(e.am)}
                        </span>{' '}
                        {e.aktion}
                        {e.von ? ` · ${e.von}` : ''}
                        {e.ergebnis !== 'ok' ? (
                            <Badge variant="outline" className="ml-2">
                                abgewiesen
                                {typeof e.details?.reason === 'string'
                                    ? `: ${e.details.reason}`
                                    : ''}
                            </Badge>
                        ) : null}
                    </li>
                ))}
            </ul>
        </>
    );
}

function NeuesToken({ wert }: { wert: string }) {
    const [kopiert, setKopiert] = useState(false);
    const kopieren = () => {
        void navigator.clipboard
            ?.writeText(wert)
            .then(() => setKopiert(true))
            .catch(() => setKopiert(false));
    };

    return (
        <Alert className="mt-6 max-w-2xl">
            <AlertTitle>Neues Token – nur jetzt sichtbar</AlertTitle>
            <AlertDescription>
                <p>
                    Jetzt kopieren und in der Integration hinterlegen (Kopfzeile{' '}
                    <code>Authorization: Bearer …</code>). Das Glossary
                    speichert nur einen Hash.
                </p>
                <p
                    className="mt-2 rounded-md bg-muted px-3 py-2 font-mono break-all"
                    translate="no"
                >
                    {wert}
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    className="mt-2"
                    onClick={kopieren}
                >
                    {kopiert ? 'Kopiert' : 'Kopieren'}
                </Button>
            </AlertDescription>
        </Alert>
    );
}

function NeuesTokenForm({ id, maxTage }: { id: string; maxTage: number }) {
    const form = useForm({ name: '', tage: 90 });
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/admin/servicekonten/${id}/token`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="mt-4 grid max-w-2xl gap-4 rounded-lg border border-border p-4 sm:grid-cols-[1fr_10rem_auto] sm:items-end"
        >
            <div className="grid gap-2">
                <Label htmlFor="tk-name">Neues Token</Label>
                <Input
                    id="tk-name"
                    placeholder="z. B. Produktion"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    required
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="tk-tage">Gültig (Tage)</Label>
                <Input
                    id="tk-tage"
                    type="number"
                    min={1}
                    max={maxTage}
                    value={form.data.tage}
                    onChange={(e) =>
                        form.setData('tage', Number(e.target.value))
                    }
                    required
                />
            </div>
            <Button type="submit" disabled={form.processing}>
                Erstellen
            </Button>
            <div className="sm:col-span-3">
                <InputError message={errors.name ?? errors.tage} />
            </div>
        </form>
    );
}
