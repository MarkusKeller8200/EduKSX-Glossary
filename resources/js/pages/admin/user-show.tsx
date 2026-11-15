import { Head, Link, router, useForm } from '@inertiajs/react';
import HistoryPanel, { type VerlaufEintrag } from '@/components/history-panel';
import type { FormEvent, ReactNode } from 'react';
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
    angaben: {
        vorname: string;
        nachname: string;
        art_einrichtung: string | null;
        einrichtung_name: string | null;
        einheitsgemeinde: boolean;
        organisation: { id: string; name: string; rolle: string } | null;
        passwort_gesetzt: boolean;
        zweiter_faktor_seit: string | null;
        anmeldedienste: {
            anbieter: string;
            seit: string | null;
            zuletzt: string | null;
        }[];
        datenschutz_version: string | null;
        datenschutz_am: string | null;
        news: boolean;
        news_am: string | null;
        hinweis_inaktiv_am: string | null;
    };
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

function datum(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleString('de-CH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '–';
}

/** Detail eines Kontos (#38): Angaben, Rollen, Sperren, zweiter Faktor, Verlauf (#417). */
export default function UserShow({
    benutzer: b,
    rollen,
    verlauf,
}: {
    benutzer: Benutzer;
    rollen: Rolle[];
    verlauf: VerlaufEintrag[];
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
                        <Button variant="outline" asChild>
                            <a href={`/admin/benutzer/${b.id}/daten`}>
                                Daten exportieren
                            </a>
                        </Button>
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

            <AlleAngaben b={b} />

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

            {/* Verlauf (#417): Änderungen an den Angaben und Aktionen, auch durch Admin und Org-Admin */}
            <HistoryPanel eintraege={verlauf} />
        </>
    );
}

/** Alle Angaben des Kontos lesend (#414); Geheimnisse (Passwort, zweiter Faktor, Tokens) erscheinen nie. */
function AlleAngaben({ b }: { b: Benutzer }) {
    const a = b.angaben;
    const zeilen: [string, ReactNode][] = [
        ['Vorname', a.vorname],
        ['Nachname', a.nachname],
        ['E-Mail', b.email],
        ['Community-Name', b.community_name ?? '–'],
        ['Funktion', b.funktion ?? '–'],
        ['Art der Einrichtung', a.art_einrichtung ?? '–'],
        [
            'Schule / Gemeinde / Organisation / Behörde',
            a.einrichtung_name ?? '–',
        ],
        ['Einheitsgemeinde', a.einheitsgemeinde ? 'ja' : 'nein'],
        [
            'Organisation',
            a.organisation ? (
                <>
                    <Link
                        href={`/admin/organisationen/${a.organisation.id}`}
                        className="text-link underline-offset-4 hover:underline"
                    >
                        {a.organisation.name}
                    </Link>{' '}
                    <span className="text-muted-foreground">
                        ({a.organisation.rolle})
                    </span>
                </>
            ) : (
                '–'
            ),
        ],
        ['Passwort', a.passwort_gesetzt ? 'gesetzt' : 'nur Anmeldedienst'],
        [
            'Zweiter Faktor',
            b.zweiter_faktor
                ? `eingerichtet seit ${datum(a.zweiter_faktor_seit)}`
                : 'nicht eingerichtet',
        ],
        [
            'Anmeldedienste',
            a.anmeldedienste.length === 0
                ? '–'
                : a.anmeldedienste
                      .map(
                          (d) =>
                              `${d.anbieter} (seit ${d.seit ?? '–'}, zuletzt ${d.zuletzt ?? '–'})`,
                      )
                      .join(', '),
        ],
        ['Angelegt', datum(b.angelegt)],
        ['Letzte Anmeldung', b.letzte_anmeldung ?? '–'],
        [
            'Datenschutzerklärung',
            a.datenschutz_am
                ? `Version ${a.datenschutz_version ?? '–'} zur Kenntnis genommen am ${datum(a.datenschutz_am)}`
                : 'noch nicht zur Kenntnis genommen',
        ],
        [
            'News',
            a.news_am
                ? `${a.news ? 'ja' : 'nein'} (beantwortet ${datum(a.news_am)})`
                : 'noch nicht gefragt',
        ],
        [
            'Hinweis wegen Inaktivität',
            a.hinweis_inaktiv_am ? datum(a.hinweis_inaktiv_am) : '–',
        ],
    ];

    return (
        <section className="mt-8">
            <h2 className="text-lg font-semibold">Angaben</h2>
            <dl className="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[18rem_1fr]">
                {zeilen.map(([k, v]) => (
                    <div key={k} className="contents">
                        <dt className="text-muted-foreground">{k}</dt>
                        <dd>{v}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
