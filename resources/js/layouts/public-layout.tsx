import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppearanceToggleTab from '@/components/appearance-tabs';
import BrandWordmark from '@/components/brand-wordmark';
import { Button } from '@/components/ui/button';
import ViewWidthTabs from '@/components/view-width-tabs';
import { versionLabel } from '@/components/version-label';
import { useViewWidth, widthClass } from '@/hooks/use-view-width';
import { hasAdminArea } from '@/lib/access';
import { cn } from '@/lib/utils';

/**
 * Öffentlicher Rahmen: Kopf mit Wortmarke, Inhalt, Fuss mit Version und Signatur (CI-Manual Abschnitt 9).
 * Kopf und Fuss bleiben bei langen Listen sichtbar (#28).
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    const { appVersion, auth, links } = usePage().props;
    const admin = hasAdminArea(auth);
    const { view } = useViewWidth();

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="sticky top-0 z-20 border-b border-border bg-background">
                <div
                    className={cn(
                        'mx-auto flex flex-wrap items-center justify-between gap-4 px-4 py-3',
                        widthClass(view, 'max-w-5xl'),
                    )}
                >
                    <Link
                        href="/"
                        className="rounded-sm text-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <BrandWordmark brand="education" suffix="Glossary" />
                    </Link>
                    <nav
                        aria-label="Hauptnavigation"
                        className="flex flex-wrap items-center gap-4 text-sm"
                    >
                        <Link
                            href="/versionen"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            Versionen
                        </Link>
                        <Button asChild size="sm" variant="outline">
                            <Link
                                href={
                                    admin
                                        ? '/admin'
                                        : auth.user
                                          ? '/konto'
                                          : '/anmelden'
                                }
                            >
                                {admin
                                    ? 'Administration'
                                    : auth.user
                                      ? 'Mein Konto'
                                      : 'Anmelden'}
                            </Link>
                        </Button>
                        <ViewWidthTabs />
                        <AppearanceToggleTab />
                    </nav>
                </div>
            </header>

            {appVersion.prototype ? (
                <div
                    role="note"
                    className="bg-secondary text-secondary-foreground"
                >
                    <p
                        className={cn(
                            'mx-auto px-4 py-2 text-sm',
                            widthClass(view, 'max-w-5xl'),
                        )}
                    >
                        In Entwicklung – Inhalte und Funktionen entstehen
                        schrittweise.
                    </p>
                </div>
            ) : null}

            <main
                className={cn(
                    'mx-auto w-full flex-1 px-4 py-8',
                    widthClass(view, 'max-w-5xl'),
                )}
            >
                {children}
            </main>

            <footer className="sticky bottom-0 z-20 border-t border-border bg-background text-sm text-muted-foreground">
                <div
                    className={cn(
                        'mx-auto flex flex-wrap items-center justify-between gap-3 px-4 py-4',
                        widthClass(view, 'max-w-5xl'),
                    )}
                >
                    <span>
                        Version{' '}
                        <Link
                            href="/versionen"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            {versionLabel(appVersion)}
                        </Link>
                        {' · '}
                        <a
                            href="/api/docs"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            API
                        </a>
                        {' · '}
                        <Link
                            href={admin ? '/admin' : '/anmelden'}
                            className="text-link underline-offset-4 hover:underline"
                        >
                            Administration
                        </Link>
                        {' · '}
                        <Link
                            href="/datenschutz"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            Datenschutz
                        </Link>
                        {' · '}
                        <Link
                            href="/legals"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            Rechtliches
                        </Link>
                        {' · '}
                        <a
                            href="https://creativecommons.org/licenses/by/4.0/deed.de"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            Daten CC BY 4.0
                        </a>
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
