import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import CookieNotice from '@/components/cookie-notice';
import SiteFooter from '@/components/site-footer';
import SiteHeader from '@/components/site-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    pageWidthClass,
    useViewWidth,
    widthClass,
} from '@/hooks/use-view-width';
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
    { href: '/admin/einstellungen', label: 'Einstellungen', recht: 'settings' },
    { href: '/admin/system', label: 'System', recht: 'system' },
];

/** Rahmen der Administration (nur angemeldet): gleiche Kopfzeile wie die Website (#146) plus Menü (#90), Breite nach Ansicht (#76, #144). */
export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth, flash } = usePage().props;
    const { component } = usePage();
    const path = usePage().url.split('?')[0];
    const { view } = useViewWidth();

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <SiteHeader area="admin">
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
            </SiteHeader>
            <CookieNotice />
            <main
                className={cn(
                    'mx-auto w-full flex-1 px-4 py-8',
                    pageWidthClass(view, 'max-w-6xl', component),
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
