import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Impressum = {
    betreiber: string;
    adresse: string;
    email: string;
    datenschutz: string;
    register: string;
    inhalt: string;
};

/** Impressum (#411): Angaben je Instanz; ohne Angaben ein Hinweis für die Betreiber. */
export default function Imprint({ impressum: i }: { impressum: Impressum }) {
    const zeilen: [string, ReactNode][] = (
        [
            ['Betreiber', i.betreiber],
            ['Adresse', i.adresse],
            [
                'Kontakt',
                i.email ? <a href={`mailto:${i.email}`}>{i.email}</a> : '',
            ],
            [
                'Datenschutz',
                i.datenschutz ? (
                    <a href={`mailto:${i.datenschutz}`}>{i.datenschutz}</a>
                ) : (
                    ''
                ),
            ],
            ['Handelsregister / UID', i.register || 'keine'],
            ['Verantwortlich für den Inhalt', i.inhalt],
        ] as [string, ReactNode][]
    ).filter(([, v]) => v !== '');

    return (
        <>
            <Head title="Impressum" />
            <h1 className="text-3xl font-semibold tracking-tight">Impressum</h1>

            {i.betreiber ? (
                <dl className="mt-6 grid max-w-2xl gap-x-6 gap-y-2 sm:grid-cols-[14rem_1fr] [&_a]:text-link [&_a]:underline [&_a]:underline-offset-4">
                    {zeilen.map(([k, v]) => (
                        <div key={k} className="contents">
                            <dt className="text-muted-foreground">{k}</dt>
                            <dd className="whitespace-pre-line">{v}</dd>
                        </div>
                    ))}
                </dl>
            ) : (
                <p className="mt-6 rounded-lg border border-border bg-muted px-4 py-3 text-sm">
                    Für diese Instanz sind noch keine Angaben hinterlegt.
                    Betreiber tragen sie unter Administration › Einstellungen ›
                    Impressum ein.
                </p>
            )}

            <section className="mt-8 max-w-prose space-y-3 text-sm text-muted-foreground">
                <h2 className="text-lg font-semibold text-foreground">
                    Hinweise
                </h2>
                <p>
                    Die Begriffe entstehen gemeinsam mit der Community und
                    werden vor der Veröffentlichung geprüft. Sie sind sorgfältig
                    zusammengestellt, aber ohne Gewähr für Vollständigkeit,
                    Richtigkeit und Aktualität; massgebend sind die genannten
                    Quellen. Fehler und fehlende Begriffe melden Sie über{' '}
                    <Link
                        href="/vorschlaege/neu"
                        className="text-link underline underline-offset-4"
                    >
                        «Begriff vorschlagen»
                    </Link>
                    .
                </p>
                <p>
                    Für Inhalte verlinkter fremder Websites sind deren Betreiber
                    verantwortlich.
                </p>
                <p>
                    Lizenzen von Daten und Code:{' '}
                    <Link
                        href="/legals"
                        className="text-link underline underline-offset-4"
                    >
                        Rechtliches
                    </Link>
                    . Personendaten und Cookies:{' '}
                    <Link
                        href="/datenschutz"
                        className="text-link underline underline-offset-4"
                    >
                        Datenschutz
                    </Link>
                    .
                </p>
            </section>
        </>
    );
}
