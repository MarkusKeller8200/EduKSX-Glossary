import { Head, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { versionLabel } from '@/components/version-label';
import type { Release } from '@/types';

/** Versionshistorie aus CHANGELOG.md, neueste zuerst (docs/KONZEPT-VERSIONIERUNG.md, #6). */
export default function Versions({ releases }: { releases: Release[] }) {
    const { appVersion, links } = usePage().props;
    const issue = (template: string) =>
        links.githubRepo
            ? `https://github.com/${links.githubRepo}/issues/new?template=${template}&version=${encodeURIComponent(versionLabel(appVersion))}`
            : null;
    const bug = issue('bug_report.yml');
    const idea = issue('feature_request.yml');

    return (
        <>
            <Head title="Versionen" />
            <h1 className="text-3xl font-semibold tracking-tight">Versionen</h1>
            <p className="mt-2 text-muted-foreground">
                Laufend: <strong>{versionLabel(appVersion)}</strong>
                {appVersion.dev
                    ? ` – geplant als ${appVersion.dev.planned}`
                    : null}
            </p>
            {bug || idea ? (
                <p className="mt-2 flex gap-4 text-sm">
                    {bug ? (
                        <a
                            href={bug}
                            className="text-link underline underline-offset-4"
                        >
                            Fehler melden
                        </a>
                    ) : null}
                    {idea ? (
                        <a
                            href={idea}
                            className="text-link underline underline-offset-4"
                        >
                            Idee vorschlagen
                        </a>
                    ) : null}
                </p>
            ) : null}

            <ol className="mt-8 space-y-8">
                {releases.map((release) => (
                    <li key={release.version}>
                        <h2 className="flex items-center gap-2 text-xl font-semibold">
                            {release.version}
                            {release.version === 'Unveröffentlicht' ? (
                                <Badge variant="secondary">
                                    Developer Release
                                </Badge>
                            ) : null}
                            {release.date ? (
                                <span className="text-sm font-normal text-muted-foreground">
                                    {release.date}
                                </span>
                            ) : null}
                        </h2>
                        {release.sections.map((section) => (
                            <section key={section.title} className="mt-3">
                                <h3 className="font-medium">{section.title}</h3>
                                <ul className="mt-1 list-disc space-y-1 pl-6">
                                    {section.items.map((item, i) => (
                                        <li key={i}>{stripMarkdown(item)}</li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </li>
                ))}
            </ol>
        </>
    );
}

/** Einfache Darstellung ohne Markdown-Baustein: Fett und Code-Markierungen entfernen. */
function stripMarkdown(text: string): string {
    return text.replace(/\*\*(.+?)\*\*/g, '$1').replace(/`([^`]+)`/g, '$1');
}
