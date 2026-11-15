import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

const REPO = 'https://github.com/MarkusKeller8200/EduKSX-Glossary';

/** Ausgelieferte Teile und ihre Lizenz (#407): was Besucherinnen und Besucher tatsächlich erhalten. */
const teile: { name: string; lizenz: string; text: string }[] = [
    {
        name: 'Anwendung (Website, API, Administration)',
        lizenz: 'AGPL-3.0-or-later',
        text: 'Eigener Code ab Version 0.15.0; bis 0.14.0 MIT.',
    },
    {
        name: 'Script zur Einbindung und Web-Komponenten',
        lizenz: 'MIT',
        text: '/embed/v1/glossary.js und /ui/v1/glossary.js, gebaut aus dem Kern «glossary-highlighter».',
    },
    {
        name: 'Browsererweiterung',
        lizenz: 'MIT',
        text: 'Chrome, Edge und Firefox; die Lizenz liegt der ZIP bei.',
    },
    {
        name: 'eduksx/core',
        lizenz: 'MIT',
        text: 'Gemeinsame Bausteine der EduKSX-Plattform.',
    },
    {
        name: 'Begriffe und Texte des Glossary',
        lizenz: 'CC BY 4.0',
        text: 'Nur eigene oder rechtmässig so lizenzierbare Texte; fremde Inhalte behalten die Rechte ihrer Urheber.',
    },
];

/** Wichtige Drittkomponenten, die mit der Website ausgeliefert werden (#407). */
const fremd: { name: string; url: string; lizenz: string; text: string }[] = [
    {
        name: 'Laravel',
        url: 'https://github.com/laravel/framework',
        lizenz: 'MIT',
        text: 'PHP-Framework.',
    },
    {
        name: 'React',
        url: 'https://github.com/facebook/react',
        lizenz: 'MIT',
        text: 'Benutzeroberfläche.',
    },
    {
        name: 'Inertia.js',
        url: 'https://github.com/inertiajs/inertia',
        lizenz: 'MIT',
        text: 'Verbindung von Laravel und React.',
    },
    {
        name: 'shadcn/ui',
        url: 'https://github.com/shadcn-ui/ui',
        lizenz: 'MIT',
        text: 'Kopierte und angepasste UI-Komponenten.',
    },
    {
        name: 'Radix UI',
        url: 'https://github.com/radix-ui/primitives',
        lizenz: 'MIT',
        text: 'Bausteine für zugängliche Bedienelemente.',
    },
    {
        name: 'Tailwind CSS',
        url: 'https://github.com/tailwindlabs/tailwindcss',
        lizenz: 'MIT',
        text: 'Gestaltung.',
    },
    {
        name: 'Lucide',
        url: 'https://github.com/lucide-icons/lucide',
        lizenz: 'ISC',
        text: 'Icons.',
    },
    {
        name: 'Inter',
        url: 'https://github.com/rsms/inter',
        lizenz: 'SIL OFL 1.1',
        text: 'Schrift, vom Glossary selbst ausgeliefert (kein externer Dienst).',
    },
    {
        name: 'Mermaid',
        url: 'https://github.com/mermaid-js/mermaid',
        lizenz: 'MIT',
        text: 'Datenmodell-Diagramm auf «Für Entwickler».',
    },
    {
        name: 'Swagger UI',
        url: 'https://github.com/swagger-api/swagger-ui',
        lizenz: 'Apache-2.0',
        text: 'Seite «API ausprobieren».',
    },
    {
        name: 'Scalar',
        url: 'https://github.com/scalar/scalar',
        lizenz: 'MIT',
        text: 'Dokumentation der API.',
    },
];

/** Rechtliches und Drittlizenzen (#407, #408): was gilt, was das heisst, wo die vollständige Liste steht. */
export default function Legals() {
    return (
        <>
            <Head title="Rechtliches und Drittlizenzen" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Rechtliches und Drittlizenzen
            </h1>
            <p className="mt-3 max-w-prose text-muted-foreground">
                EduKSX-Glossary ist freie Software mit offenen Daten. Hier
                steht, unter welchen Lizenzen Code und Inhalte stehen, was das
                in der Praxis heisst und welche fremden Komponenten enthalten
                sind.
            </p>

            <Abschnitt titel="Was heisst das? – kurz erklärt">
                <div className="grid gap-4 md:grid-cols-2">
                    <Kasten titel="Begriffe: CC BY 4.0">
                        Sie dürfen die veröffentlichten Begriffe und Texte frei
                        nutzen, kopieren, verändern und weitergeben – auch
                        kommerziell. Einzige Bedingung: die Quelle nennen, z. B.
                        «EduKSX-Glossary (glossary.eduksx.ch), CC BY 4.0». API,
                        Markdown- und PDF-Export, Erklärbox und Erweiterung
                        nennen die Quelle bereits; bei Excel und CSV bitte
                        selbst ergänzen.{' '}
                        <a
                            href="https://creativecommons.org/licenses/by/4.0/deed.de"
                            className="text-link underline underline-offset-4"
                        >
                            Lizenztext
                        </a>
                    </Kasten>
                    <Kasten titel="Code: AGPL-3.0">
                        Sie dürfen das Glossary selbst betreiben, ändern und
                        weitergeben. Wer eine <em>geänderte</em> Fassung im Netz
                        anbietet, muss den Nutzerinnen und Nutzern deren
                        Quellcode zugänglich machen – unter derselben Lizenz.
                        Der Quellcode der hier laufenden Version ist in der
                        Fusszeile verlinkt.{' '}
                        <a
                            href={`${REPO}/blob/main/LICENSE`}
                            className="text-link underline underline-offset-4"
                        >
                            Lizenztext
                        </a>
                    </Kasten>
                    <Kasten titel="Script und Erweiterung: MIT">
                        Das Script zur Einbindung und die Browsererweiterung
                        dürfen Sie ohne Auflagen in eigene Seiten und Produkte
                        übernehmen; nur der Lizenzhinweis muss erhalten bleiben.
                    </Kasten>
                    <Kasten titel="Marke">
                        Die Namen und Logos «EduKSX» und «MarKusSXCH» sind nicht
                        Teil der Lizenzen. Eigene Installationen oder
                        Abwandlungen bitte unter eigenem Namen anbieten.
                    </Kasten>
                </div>
            </Abschnitt>

            <Abschnitt titel="Lizenzen der Teile">
                <Liste
                    eintraege={teile.map((t) => ({
                        key: t.name,
                        name: <span className="font-semibold">{t.name}</span>,
                        lizenz: t.lizenz,
                        text: t.text,
                    }))}
                />
            </Abschnitt>

            <Abschnitt titel="Drittkomponenten (Auswahl)">
                <Liste
                    eintraege={fremd.map((f) => ({
                        key: f.name,
                        name: (
                            <a
                                className="font-semibold text-link underline underline-offset-4"
                                href={f.url}
                            >
                                {f.name}
                            </a>
                        ),
                        lizenz: f.lizenz,
                        text: f.text,
                    }))}
                />
                <p className="mt-4 text-sm">
                    Vollständig, mit allen PHP- und JavaScript-Paketen samt
                    Versionen:{' '}
                    <a
                        className="text-link underline underline-offset-4"
                        href={`${REPO}/releases/latest/download/license-report.md`}
                    >
                        Lizenzbericht des neuesten Release
                    </a>
                    . Herkunft übernommenen Codes und Prüfstand:{' '}
                    <a
                        className="text-link underline underline-offset-4"
                        href={`${REPO}/blob/main/docs/LEGAL.md`}
                    >
                        Lizenz- und Herkunftsdokumentation
                    </a>
                    .
                </p>
            </Abschnitt>

            <Abschnitt titel="Datenschutz und Cookies">
                <p className="text-sm">
                    Das Glossary setzt nur technisch nötige Cookies und keine
                    Analyse- oder Werbedienste. Details in der{' '}
                    <Link
                        href="/datenschutz"
                        className="text-link underline underline-offset-4"
                    >
                        Datenschutzerklärung
                    </Link>
                    .
                </p>
            </Abschnitt>

            <p className="mt-8 text-sm text-muted-foreground">
                Die genannten Marken, Namen und Logos gehören ihren jeweiligen
                Inhabern. Eine Nennung bedeutet keine Unterstützung oder
                Partnerschaft. Diese Übersicht ist keine Rechtsberatung.
            </p>
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
            <h2 className="text-xl font-semibold">{titel}</h2>
            <div className="mt-3">{children}</div>
        </section>
    );
}

function Kasten({ titel, children }: { titel: string; children: ReactNode }) {
    return (
        <div className="rounded-lg border border-border p-4">
            <h3 className="font-semibold">{titel}</h3>
            <p className="mt-1 text-sm text-muted-foreground">{children}</p>
        </div>
    );
}

function Liste({
    eintraege,
}: {
    eintraege: { key: string; name: ReactNode; lizenz: string; text: string }[];
}) {
    return (
        <ul className="divide-y divide-border border-y border-border">
            {eintraege.map((e) => (
                <li key={e.key} className="py-3">
                    <div className="flex flex-wrap items-baseline gap-3">
                        {e.name}
                        <span className="rounded-md bg-secondary px-2 py-0.5 text-xs text-secondary-foreground">
                            {e.lizenz}
                        </span>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {e.text}
                    </p>
                </li>
            ))}
        </ul>
    );
}
