import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { confirmAction } from '@/components/confirm-dialog';

type Benutzer = {
    id: string;
    name: string;
    email: string;
    community_name: string | null;
    funktion: string | null;
    einrichtung: string;
    letzte_anmeldung: string | null;
    status: string;
    gesperrt_am: string | null;
    zweiter_faktor: boolean;
    angelegt: string | null;
    rechte: string[];
    selbst: boolean;
};

type Rolle = {
    key: string;
    name: string;
    beschreibung: string | null;
    zugewiesen: boolean;
    aenderbar: boolean;
};

type Eintrag = {
    id: number;
    am: string;
    aktion: string;
    rolle: string | null;
    von: string;
    ergebnis: string;
    grund: string | null;
};

function datum(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleString('de-CH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '–';
}

/** Detail eines Kontos (#38): Rollen, Sperren, zweiter Faktor, Protokoll. */
export default function UserShow({
    benutzer: b,
    rollen,
    protokoll,
}: {
    benutzer: Benutzer;
    rollen: Rolle[];
    protokoll: Eintrag[];
}) {
    const form = useForm<{ rollen: string[] }>({
        rollen: rollen.filter((r) => r.zugewiesen).map((r) => r.key),
    });
    const [sending, setSending] = useState(false);
    const errors = form.errors as Record<string, string | undefined>;
    const umschalten = (key: string, an: boolean) =>
        form.setData(
            'rollen',
            an
                ? [...form.data.rollen, key]
                : form.data.rollen.filter((k) => k !== key),
        );
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/admin/benutzer/${b.id}/rollen`, { preserveScroll: true });
    };
    const aktion = async (pfad: string, frage: string) => {
        if (!(await confirmAction(frage))) {
            return;
        }
        setSending(true);
        router.post(
            `/admin/benutzer/${b.id}/${pfad}`,
            {},
            { preserveScroll: true, onFinish: () => setSending(false) },
        );
    };

    return (
        <>
            <Head title={b.name} />
            <p className="text-sm">
                <Link
                    href="/admin/benutzer"
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← Benutzer
                </Link>
            </p>
            <div className="mt-2 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {b.name}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {b.email} · angelegt {datum(b.angelegt)} · letzte
                        Anmeldung {b.letzte_anmeldung ?? '–'}
                    </p>
                    {b.community_name || b.funktion || b.einrichtung ? (
                        <p className="mt-1 text-sm">
                            {[
                                b.community_name
                                    ? `Community-Name «${b.community_name}»`
                                    : null,
                                b.funktion,
                                b.einrichtung,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    ) : null}
                    <p className="mt-2 flex flex-wrap gap-2 text-sm">
                        {b.status === 'blocked' ? (
                            <Badge variant="secondary">
                                gesperrt seit {datum(b.gesperrt_am)}
                            </Badge>
                        ) : (
                            <Badge>aktiv</Badge>
                        )}
                        <Badge variant="outline">
                            {b.zweiter_faktor
                                ? 'zweiter Faktor eingerichtet'
                                : 'ohne zweiten Faktor'}
                        </Badge>
                    </p>
                </div>
                {b.selbst ? null : (
                    <div className="flex flex-wrap gap-2">
                        {b.status === 'blocked' ? (
                            <Button
                                variant="outline"
                                disabled={sending}
                                onClick={() =>
                                    aktion(
                                        'entsperren',
                                        'Konto wieder freigeben?',
                                    )
                                }
                            >
                                Entsperren
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                disabled={sending}
                                onClick={() =>
                                    aktion(
                                        'sperren',
                                        'Konto sperren? Laufende Sitzungen werden sofort beendet.',
                                    )
                                }
                            >
                                Sperren
                            </Button>
                        )}
                        {b.status !== 'blocked' ? (
                            <Button
                                variant="outline"
                                disabled={sending}
                                onClick={() =>
                                    aktion(
                                        'passwort-link',
                                        'Link zum Zurücksetzen des Passworts an die E-Mail-Adresse des Kontos senden? Das bisherige Passwort bleibt gültig, bis ein neues gesetzt ist.',
                                    )
                                }
                            >
                                Passwort-Link senden
                            </Button>
                        ) : null}
                        {b.zweiter_faktor ? (
                            <Button
                                variant="outline"
                                disabled={sending}
                                onClick={() =>
                                    aktion(
                                        'zwei-faktor',
                                        'Zweiten Faktor zurücksetzen? Nur nach überprüfter Identität (z. B. Handy verloren).',
                                    )
                                }
                            >
                                Zweiten Faktor zurücksetzen
                            </Button>
                        ) : null}
                    </div>
                )}
            </div>

            {errors.konto ? (
                <Alert variant="destructive" className="mt-6">
                    <AlertDescription>{errors.konto}</AlertDescription>
                </Alert>
            ) : null}

            <h2 className="mt-8 text-lg font-semibold">Rollen</h2>
            <p className="mt-1 text-sm text-muted-foreground">
                Ohne Rolle kann das Konto nur lesen (wie Gast). Grau: ausserhalb
                der eigenen Befugnis (Developer nur durch Developer oder den
                Serverbefehl).
            </p>
            <form onSubmit={submit} className="mt-3 grid max-w-2xl gap-3">
                {rollen.map((r) => (
                    <label
                        key={r.key}
                        className="flex items-start gap-3 rounded-md border border-border p-3"
                    >
                        <Checkbox
                            className="mt-0.5"
                            checked={form.data.rollen.includes(r.key)}
                            disabled={!r.aenderbar}
                            onCheckedChange={(v) =>
                                umschalten(r.key, v === true)
                            }
                        />
                        <span>
                            <span className="font-medium">{r.name}</span>
                            {r.beschreibung ? (
                                <span className="block text-sm text-muted-foreground">
                                    {r.beschreibung}
                                </span>
                            ) : null}
                        </span>
                    </label>
                ))}
                <InputError message={errors.rollen} />
                <div>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Rollen speichern
                    </Button>
                </div>
            </form>

            <h2 className="mt-8 text-lg font-semibold">Effektive Rechte</h2>
            <p className="mt-2 flex flex-wrap gap-1">
                {b.rechte.map((r) => (
                    <Badge key={r} variant="outline">
                        {r}
                    </Badge>
                ))}
            </p>

            <h2 className="mt-8 text-lg font-semibold">Protokoll</h2>
            {protokoll.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Noch keine Einträge.
                </p>
            ) : (
                <ol className="mt-3 divide-y divide-border rounded-lg border border-border text-sm">
                    {protokoll.map((e) => (
                        <li
                            key={e.id}
                            className="flex flex-wrap gap-x-3 gap-y-1 px-3 py-2"
                        >
                            <span className="text-muted-foreground">
                                {datum(e.am)}
                            </span>
                            <span className="font-medium">
                                {e.aktion}
                                {e.rolle ? ` «${e.rolle}»` : ''}
                            </span>
                            <span>von {e.von}</span>
                            {e.ergebnis === 'denied' ? (
                                <Badge variant="secondary">
                                    abgewiesen{e.grund ? `: ${e.grund}` : ''}
                                </Badge>
                            ) : null}
                        </li>
                    ))}
                </ol>
            )}
        </>
    );
}
