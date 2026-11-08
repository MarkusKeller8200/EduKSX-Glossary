import { Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import BrandWordmark from '@/components/brand-wordmark';
import CookieNotice from '@/components/cookie-notice';
import SiteFooter from '@/components/site-footer';
import ViewWidthTabs from '@/components/view-width-tabs';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useViewWidth, widthClass } from '@/hooks/use-view-width';
import { can } from '@/lib/access';
import { cn } from '@/lib/utils';

/** Navigation; Einträge nur mit dem nötigen Recht (#36). */
const NAV = [
    { href: '/admin', label: 'Übersicht', recht: null },
    { href: '/admin/begriffe', label: 'Begriffe', recht: 'edit-terms' },
    { href: '/admin/vorschlaege', label: 'Vorschläge', recht: 'moderate' },
    { href: '/admin/doppelte', label: 'Doppelte', recht: 'edit-terms' },
    { href: '/admin/altwerte', label: 'Altwerte', recht: 'edit-terms' },
    { href: '/admin/pruefung', label: 'Prüfung', recht: 'edit-terms' },
    { href: '/admin/listen', label: 'Listen', recht: 'lists' },
    { href: '/admin/import', label: 'Excel-Import', recht: 'import' },
    { href: '/admin/benutzer', label: 'Benutzer', recht: 'manage-users' },
    {
        href: '/admin/servicekonten',
        label: 'Servicekonten',
        recht: 'service-tokens',
    },
    { href: '/admin/system', label: 'System', recht: 'system' },
];

/** Rahmen der Administration (nur angemeldet): Kopf in zwei Zeilen (#90), Breite nach gewählter Ansicht (#76). */
export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth, flash } = usePage().props;
    const path = usePage().url.split('?')[0];
    const { view } = useViewWidth();

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="sticky top-0 z-20 border-b border-border bg-sidebar">
                {/* Zeile 1 (#90): Titel links, Person und Abmelden auf gleicher Höhe rechts */}
                <div
                    className={cn(
                        'mx-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 pt-3 pb-2',
                        widthClass(view, 'max-w-6xl'),
                    )}
                >
                    <Link href="/admin" className="text-lg">
                        <BrandWordmark brand="education" suffix="Glossary" />
                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                            Administration
                        </span>
                    </Link>
                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <ViewWidthTabs />
                        <Link
                            href="/"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            Website
                        </Link>
                        <Link
                            href="/konto"
                            className="text-muted-foreground underline-offset-4 hover:underline"
                            title="Mein Konto"
                        >
                            {auth.user?.name}
                        </Link>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.post('/abmelden')}
                        >
                            Abmelden
                        </Button>
                    </div>
                </div>
                {/* Zeile 2 (#90): alle Menüpunkte */}
                <nav
                    aria-label="Administration"
                    className={cn(
                        'mx-auto flex flex-wrap items-center gap-1 px-4 pb-2 text-sm',
                        widthClass(view, 'max-w-6xl'),
                    )}
                >
                    {NAV.filter(
                        (item) => item.recht === null || can(auth, item.recht),
                    ).map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            aria-current={
                                path === item.href ? 'page' : undefined
                            }
                            className={cn(
                                'rounded-md px-3 py-1.5 hover:bg-accent hover:text-accent-foreground',
                                path === item.href &&
                                    'bg-secondary font-medium text-secondary-foreground',
                            )}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
            </header>
            <CookieNotice />
            <main
                className={cn(
                    'mx-auto w-full flex-1 px-4 py-8',
                    widthClass(view, 'max-w-6xl'),
                )}
            >
                {flash.status ? (
                    <Alert className="mb-6" role="status">
                        <AlertDescription>{flash.status}</AlertDescription>
                    </Alert>
                ) : null}
                {children}
            </main>
            <SiteFooter narrow="max-w-6xl" />
        </div>
    );
}
