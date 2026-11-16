import { Head, Link, router } from '@inertiajs/react';
import { confirmAction } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import HistoryPanel, { type VerlaufEintrag } from '@/components/history-panel';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';

type Mitglied = {
    user_id: string;
    name: string;
    email: string;
    rolle: string;
    status: string;
    zweiter_faktor: boolean;
    seit: string | null;
};

type Organisation = {
    id: string;
    name: string;
    plattform_id: string | null;
    microsoft_tenant: string | null;
    domain: string | null;
    domain_automatisch: boolean;
    angelegt: string | null;
    angelegt_von: string | null;
    mitglieder: Mitglied[];
    einladungen: { email: string; gueltig_bis: string; abgelaufen: boolean }[];
    servicekonten: {
        id: string;
        name: string;
        zweck: string;
        status: string;
        scopes: string[];
    }[];
};

function datum(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleString('de-CH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '–';
}

/** Detail einer Organisation in der Administration (#414): alle Angaben lesend, ohne Token und Geheimnisse. */
export default function OrganizationShow({
    organisation: o,
    verlauf,
}: {
    organisation: Organisation;
    verlauf: VerlaufEintrag[];
}) {
    return (
        <>
            <Head title={o.name} />
            <p className="text-sm">
                <Link
                    href="/admin/organisationen"
                    className="text-link underline-offset-4 hover:underline"
                >
                    ← Organisationen
                </Link>
            </p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                {o.name}
            </h1>

            <Abschnitt titel="Angaben">
                <Angaben
                    zeilen={[
                        ['Name', o.name],
                        ['Mandant (ID)', <code key="id">{o.id}</code>],
                        ['Plattform-ID', o.plattform_id ?? '–'],
                        ['Microsoft-Tenant', o.microsoft_tenant ?? '–'],
                        [
                            'Domain',
                            o.domain ? (
                                <span key="domain">
                                    <code>@{o.domain}</code>
                                    {o.domain_automatisch
                                        ? ' – Konten werden automatisch aufgenommen'
                                        : ''}{' '}
                                    {/* Zuordnung aufheben (#462), z. B. bei zu Unrecht beanspruchter Domain */}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="ml-2"
                                        onClick={async () => {
                                            if (
                                                await confirmAction(
                                                    `Domain @${o.domain} von «${o.name}» lösen? Die automatische Aufnahme wird ausgeschaltet; bestehende Mitglieder bleiben.`,
                                                    { confirmLabel: 'Lösen' },
                                                )
                                            ) {
                                                router.delete(
                                                    `/admin/organisationen/${o.id}/domain`,
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                    >
                                        Lösen
                                    </Button>
                                </span>
                            ) : (
                                '–'
                            ),
                        ],
                        ['Angelegt', datum(o.angelegt)],
                        ['Angelegt von', o.angelegt_von ?? '–'],
                    ]}
                />
            </Abschnitt>

            <Abschnitt titel={`Mitglieder (${o.mitglieder.length})`}>
                {o.mitglieder.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Keine Mitglieder.
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border border-border text-sm">
                        {o.mitglieder.map((m) => (
                            <li
                                key={m.user_id}
                                className="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                            >
                                <span>
                                    <Link
                                        href={`/admin/benutzer/${m.user_id}`}
                                        className="font-medium text-link underline-offset-4 hover:underline"
                                    >
                                        {m.name}
                                    </Link>
                                    {m.email ? (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {m.email}
                                        </span>
                                    ) : null}
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · seit {m.seit ?? '–'}
                                    </span>
                                </span>
                                <span className="flex flex-wrap gap-1">
                                    <Badge variant="outline">{m.rolle}</Badge>
                                    {m.status === 'blocked' ? (
                                        <Badge variant="secondary">
                                            gesperrt
                                        </Badge>
                                    ) : null}
                                    {m.zweiter_faktor ? null : (
                                        <Badge variant="outline">
                                            ohne zweiten Faktor
                                        </Badge>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Abschnitt>

            <Abschnitt titel={`Einladungen (${o.einladungen.length})`}>
                {o.einladungen.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Keine Einladungen.
                    </p>
                ) : (
                    <ul className="grid gap-1 text-sm">
                        {o.einladungen.map((e) => (
                            <li key={e.email}>
                                {e.email}{' '}
                                <span className="text-muted-foreground">
                                    ·{' '}
                                    {e.abgelaufen
                                        ? `abgelaufen am ${e.gueltig_bis}`
                                        : `gültig bis ${e.gueltig_bis}`}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Abschnitt>

            <Abschnitt titel={`Servicekonten (${o.servicekonten.length})`}>
                {o.servicekonten.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Keine Servicekonten.
                    </p>
                ) : (
                    <ul className="grid gap-1 text-sm">
                        {o.servicekonten.map((s) => (
                            <li key={s.id}>
                                <Link
                                    href={`/admin/servicekonten/${s.id}`}
                                    className="font-medium text-link underline-offset-4 hover:underline"
                                >
                                    {s.name}
                                </Link>
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {s.zweck} · {s.status} ·{' '}
                                    {s.scopes.join(', ')}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Abschnitt>

            <HistoryPanel eintraege={verlauf} />
        </>
    );
}

function Abschnitt({
    titel,
    children,
}: {
    titel: string;
    children: ReactNode;
}) {
    return (
        <section className="mt-8">
            <h2 className="text-lg font-semibold">{titel}</h2>
            <div className="mt-3">{children}</div>
        </section>
    );
}

function Angaben({ zeilen }: { zeilen: [string, ReactNode][] }) {
    return (
        <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[14rem_1fr]">
            {zeilen.map(([k, v]) => (
                <div key={k} className="contents">
                    <dt className="text-muted-foreground">{k}</dt>
                    <dd>{v}</dd>
                </div>
            ))}
        </dl>
    );
}
