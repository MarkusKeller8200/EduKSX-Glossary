import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppearanceToggleTab from '@/components/appearance-tabs';
import BrandWordmark from '@/components/brand-wordmark';
import { versionLabel } from '@/components/version-label';

/** Öffentlicher Rahmen: Kopf mit Wortmarke, Inhalt, Fuss mit Version und Signatur (CI-Manual Abschnitt 9). */
export default function PublicLayout({ children }: { children: ReactNode }) {
    const { appVersion, auth, links } = usePage().props;

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="border-b border-border">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
                    <Link
                        href="/"
                        className="rounded-sm text-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <BrandWordmark brand="education" suffix="Glossary" />
                    </Link>
                    <nav
                        aria-label="Hauptnavigation"
                        className="flex items-center gap-4 text-sm"
                    >
                        <Link
                            href="/versionen"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            Versionen
                        </Link>
                        {auth.user ? (
                            <Link
                                href="/admin"
                                className="text-link underline-offset-4 hover:underline"
                            >
                                Administration
                            </Link>
                        ) : null}
                        <AppearanceToggleTab />
                    </nav>
                </div>
            </header>

            {appVersion.prototype ? (
                <div
                    role="note"
                    className="bg-secondary text-secondary-foreground"
                >
                    <p className="mx-auto max-w-5xl px-4 py-2 text-sm">
                        In Entwicklung – Inhalte und Funktionen entstehen
                        schrittweise.
                    </p>
                </div>
            ) : null}

            <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
                {children}
            </main>

            <footer className="border-t border-border text-sm text-muted-foreground">
                <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                    <span>
                        Version{' '}
                        <Link
                            href="/versionen"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            {versionLabel(appVersion)}
                        </Link>
                        {links.pages ? (
                            <>
                                {' · '}
                                <a
                                    href={links.pages}
                                    className="text-link underline-offset-4 hover:underline"
                                >
                                    Lesefassung auf GitHub Pages
                                </a>
                            </>
                        ) : null}
                    </span>
                    <a
                        href="https://markussx.ch/eduksx"
                        className="underline-offset-4 hover:underline"
                    >
                        Developed by <BrandWordmark brand="developer" />
                    </a>
                </div>
            </footer>
        </div>
    );
}
