import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type Einbindung = {
    version: string;
    datei: string;
    neueste: string;
    integrity: string;
};

const OPTIONEN: [string, string][] = [
    [
        'data-scope',
        'CSS-Selektor des Bereichs, der durchsucht wird. Vorgabe «main, article, [data-glossary]»; «none» = nur <glossary-term>.',
    ],
    [
        'data-names',
        '«true»: auch ausgeschriebene Bezeichnungen und Schreibweisen erkennen (sonst nur Abkürzungen).',
    ],
    [
        'data-first-only',
        '«true»: je Begriff nur die erste Fundstelle markieren.',
    ],
    ['data-categories', 'Nur Begriffe dieser Kategorien (Labels, mit Komma).'],
    ['data-max', 'Höchstens so viele Markierungen (Vorgabe 300).'],
    ['data-observe', '«true»: nachgeladene Inhalte ebenfalls durchsuchen.'],
];

/** «Für Entwickler» (#155, DEC-026): Glossary in eigene Webseiten einbinden. */
export default function Developers({
    origin,
    einbindung,
}: {
    origin: string;
    einbindung: Einbindung | null;
}) {
    const fest = einbindung
        ? `<script src="${origin}${einbindung.datei}"
        integrity="${einbindung.integrity}"
        crossorigin="anonymous" defer
        data-scope="#inhalt" data-first-only="true"></script>`
        : '';
    const neueste = einbindung
        ? `<script src="${origin}${einbindung.neueste}" defer data-scope="#inhalt"></script>`
        : '';

    return (
        <>
            <Head title="Für Entwickler" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Für Entwickler
            </h1>
            <p className="mt-3 max-w-prose text-muted-foreground">
                Binden Sie das Glossary in Ihre Webseite ein: Abkürzungen und
                Begriffe werden fein unterstrichen und beim Darüberfahren oder
                mit der Tabulatortaste erklärt – mit Abkürzung, Bezeichnung,
                Kurztext und Link ins Glossary. Die Suche läuft im Browser Ihrer
                Besucher; es werden keine Inhalte Ihrer Seite an das Glossary
                gesendet, keine Cookies gesetzt und nichts aufgezeichnet.
            </p>
            <p className="mt-3 text-sm">
                <a
                    href="/embed/demo.html"
                    className="text-link underline underline-offset-4"
                >
                    Testseite ansehen
                </a>
            </p>

            <Abschnitt titel="1. Mit einer Zeile einbinden">
                {einbindung ? (
                    <>
                        <p className="text-sm text-muted-foreground">
                            Empfohlen: feste Version {einbindung.version} mit
                            Prüfsumme (SRI). Der Browser lädt das Script nur,
                            wenn es genau dieser Version entspricht.
                        </p>
                        <Code text={fest} />
                        <p className="mt-4 text-sm text-muted-foreground">
                            Oder immer die neueste 1.x (ohne Prüfsumme, nur
                            kompatible Änderungen):
                        </p>
                        <Code text={neueste} />
                    </>
                ) : (
                    <p className="text-sm">Script noch nicht gebaut.</p>
                )}
            </Abschnitt>

            <Abschnitt titel="2. Begriffe ausdrücklich markieren">
                <p className="text-sm text-muted-foreground">
                    Ohne automatische Suche (z. B. mit{' '}
                    <code>data-scope="none"</code>): Sie bestimmen, was erklärt
                    wird. Den Code zeigt jede Seite eines Begriffs unter
                    «Permalink» (<code>/g/7KQ2MX</code> → <code>7KQ2MX</code>).
                    Ohne Inhalt erscheint die Abkürzung.
                </p>
                <Code
                    text={'<glossary-term code="7KQ2MX">SL</glossary-term>'}
                />
            </Abschnitt>

            <Abschnitt titel="Optionen">
                <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[10rem_1fr]">
                    {OPTIONEN.map(([name, text]) => (
                        <div key={name} className="contents">
                            <dt>
                                <code>{name}</code>
                            </dt>
                            <dd className="text-muted-foreground">{text}</dd>
                        </div>
                    ))}
                </dl>
                <p className="mt-3 text-sm text-muted-foreground">
                    Einzelne Bereiche schliessen Sie mit{' '}
                    <code>data-glossary-ignore</code> aus. Nach dem Nachladen
                    eigener Inhalte: <code>EduksxGlossary.scan(element)</code>.
                </p>
            </Abschnitt>

            <Abschnitt titel="Sicherheit">
                <ul className="list-disc space-y-1 pl-6 text-sm">
                    <li>
                        Mit Content-Security-Policy:{' '}
                        <code>script-src {origin}</code> und{' '}
                        <code>connect-src {origin}</code> erlauben.
                    </li>
                    <li>
                        Die Erklärbox zeigt Inhalte nur als Text und verlinkt
                        nur auf <code>{origin}/g/…</code>.
                    </li>
                    <li>
                        Der Index wird höchstens einmal täglich geladen (ETag)
                        und im Browser zwischengespeichert.
                    </li>
                </ul>
            </Abschnitt>

            <Abschnitt titel="3. Nur die Daten">
                <p className="text-sm text-muted-foreground">
                    Eigene Darstellung? Die öffentliche API liefert alles
                    Nötige, ohne Schlüssel.
                </p>
                <ul className="mt-2 list-disc space-y-1 pl-6 text-sm">
                    <li>
                        <a
                            href="/api/v1/index"
                            className="text-link underline underline-offset-4"
                        >
                            /api/v1/index
                        </a>{' '}
                        – kompakter Index für Erkennung und Erklärbox
                    </li>
                    <li>
                        <a
                            href="/api/docs"
                            className="text-link underline underline-offset-4"
                        >
                            Dokumentation
                        </a>
                        ,{' '}
                        <a
                            href="/api/swagger"
                            className="text-link underline underline-offset-4"
                        >
                            Swagger (ausprobieren)
                        </a>{' '}
                        und{' '}
                        <a
                            href="/api/postman.json"
                            className="text-link underline underline-offset-4"
                        >
                            Postman-Sammlung
                        </a>
                    </li>
                </ul>
            </Abschnitt>

            <Abschnitt titel="Lizenz">
                <p className="text-sm text-muted-foreground">
                    Script unter MIT-Lizenz, Daten unter CC BY 4.0 – die
                    Erklärbox nennt die Quelle «EduKSX-Glossary». Fragen und
                    Fehler:{' '}
                    <Link
                        href="/hilfe"
                        className="text-link underline underline-offset-4"
                    >
                        Hilfe
                    </Link>
                    .
                </p>
            </Abschnitt>
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
        <section className="mt-8 max-w-3xl">
            <h2 className="text-xl font-semibold">{titel}</h2>
            <div className="mt-3">{children}</div>
        </section>
    );
}

function Code({ text }: { text: string }) {
    const [kopiert, setKopiert] = useState(false);

    return (
        <div className="mt-2 rounded-lg border border-border bg-muted/40">
            <pre className="overflow-x-auto p-3 text-xs">
                <code>{text}</code>
            </pre>
            {typeof navigator !== 'undefined' && navigator.clipboard ? (
                <div className="border-t border-border p-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            navigator.clipboard.writeText(text).then(() => {
                                setKopiert(true);
                                window.setTimeout(
                                    () => setKopiert(false),
                                    2000,
                                );
                            })
                        }
                    >
                        {kopiert ? 'Kopiert' : 'Kopieren'}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
