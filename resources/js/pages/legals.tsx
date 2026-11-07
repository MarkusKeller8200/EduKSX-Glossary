import { Head } from '@inertiajs/react';

const origins = [
    { name: 'sek-intranet', url: 'https://github.com/MarkusKeller8200/sek-intranet', license: 'Eigenes Repository', detail: 'Eigene GUI-Bausteine und Infrastrukturteile. Nur darin enthaltene fremde Komponenten und deren Lizenzpflichten sind zu prüfen.' },
    { name: 'shadcn/ui', url: 'https://github.com/shadcn-ui/ui', license: 'MIT', detail: 'Kopierte und angepasste UI-Komponenten; Copyright und vollständigen Lizenzhinweis mitliefern.' },
    { name: 'Laravel', url: 'https://github.com/laravel/framework', license: 'MIT', detail: 'Framework und Anwendungsgrundgerüst; Originalherkunft kopierter Skeleton-Dateien prüfen.' },
    { name: 'React', url: 'https://github.com/facebook/react', license: 'MIT', detail: 'Benutzeroberfläche.' },
    { name: 'Lucide', url: 'https://github.com/lucide-icons/lucide', license: 'ISC', detail: 'Icons; Copyright- und Lizenzhinweise berücksichtigen.' },
    { name: 'Inter', url: 'https://github.com/rsms/inter', license: 'SIL OFL 1.1', detail: 'Schriftwahl; tatsächliche Auslieferung von Font-Dateien und Lizenzhinweise noch prüfen.' },
    { name: 'my-github-blueprint', url: 'https://github.com/MarkusKeller8200/my-github-blueprint', license: 'Eigenes Repository', detail: 'Eigene GitHub-Konfigurationen und Workflows. Lizenzpflichten sind nur für gegebenenfalls enthaltene Fremdanteile zu prüfen.' },
];

export default function Legals() {
    return (
        <>
            <Head title="Rechtliches und Drittlizenzen" />
            <h1 className="text-3xl font-semibold tracking-tight">Rechtliches und Drittlizenzen</h1>
            <p className="mt-3 text-muted-foreground">
                EduKSX-Glossary verwendet eigene Entwicklungen, Komponenten aus anderen Projekten
                und freie Software. Diese Übersicht nennt bekannte Herkunftsprojekte und Lizenzgrundlagen.
            </p>
            <div className="mt-6 rounded-lg border border-border bg-muted px-4 py-3 text-sm">
                <strong>Lizenzprüfung im Aufbau.</strong> Die Aufstellung ist noch kein vollständiges
                Lizenzinventar und keine abschliessende rechtliche Freigabe. Insbesondere die
                Lizenzpflichten fremder Bestandteile in übernommenem Code und die Softwarelizenz für eigene
                EduKSX-Glossary-Programmteile müssen noch geklärt werden.
            </div>
            <section className="mt-8 space-y-3">
                <h2 className="text-xl font-semibold">Lizenzierung des Projekts</h2>
                <p>Für den eigenen Anwendungscode ist noch keine endgültige Softwarelizenz festgelegt.
                    Der öffentliche GitHub-Code ist dadurch nicht automatisch zur beliebigen Weiterverwendung freigegeben.</p>
                <p>Für selbst erstellte und entsprechend lizenzierbare Glossary-Texte ist derzeit
                    <a href="https://creativecommons.org/licenses/by/4.0/deed.de" className="ml-1 text-link underline underline-offset-4">CC BY 4.0</a> vorgesehen.
                    Fremde Inhalte bleiben an die Rechte ihrer jeweiligen Urheber gebunden.</p>
            </section>
            <section className="mt-8">
                <h2 className="text-xl font-semibold">Verwendete Projekte und Code-Herkünfte</h2>
                <ul className="mt-4 divide-y divide-border border-y border-border">
                    {origins.map((origin) => (
                        <li key={origin.name} className="py-4">
                            <div className="flex flex-wrap items-baseline gap-3">
                                <a className="font-semibold text-link underline underline-offset-4" href={origin.url}>{origin.name}</a>
                                <span className="rounded-md bg-secondary px-2 py-0.5 text-xs text-secondary-foreground">{origin.license}</span>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">{origin.detail}</p>
                        </li>
                    ))}
                </ul>
            </section>
            <section className="mt-8 space-y-3">
                <h2 className="text-xl font-semibold">Weitere Bibliotheken</h2>
                <p>Die Anwendung verwendet unter anderem Inertia.js, Radix UI, Tailwind CSS,
                    Vite Plus sowie PHP- und JavaScript-Abhängigkeiten. Ihre exakten Versionen
                    und vollständigen Lizenztexte werden anhand der Paket-Lockfiles erfasst.</p>
                <p>Der vollständige Prüfstand, die Herkunftsliste und die noch offenen
                    Lizenzpflichten sind in der{' '}
                    <a className="text-link underline underline-offset-4" href="https://github.com/MarkusKeller8200/EduKSX-Glossary/blob/main/docs/LEGAL.md">
                        Lizenz- und Herkunftsdokumentation auf GitHub
                    </a> festgehalten.</p>
            </section>
            <p className="mt-8 text-sm text-muted-foreground">Die genannten Marken, Namen und Logos
                gehören ihren jeweiligen Inhabern. Eine Nennung bedeutet keine Unterstützung oder Partnerschaft.</p>
        </>
    );
}
