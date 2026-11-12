import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import ConfirmHost from '@/components/confirm-dialog';
import CookieNotice from '@/components/cookie-notice';
import UpdateNotice from '@/components/update-notice';
import SiteFooter from '@/components/site-footer';
import NewsQuestion from '@/components/news-question';
import SiteHeader from '@/components/site-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    pageWidthClass,
    useViewWidth,
    widthClass,
} from '@/hooks/use-view-width';
import { can } from '@/lib/access';
import { ADMIN_NAV as NAV } from '@/lib/admin-nav';
import { cn } from '@/lib/utils';

/** Rahmen der Administration (nur angemeldet): gleiche Kopfzeile wie die Website (#146) plus Menü (#90), Breite nach Ansicht (#76, #144). */
export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth, flash } = usePage().props;
    const { component } = usePage();
    const path = usePage().url.split('?')[0];
    const { view } = useViewWidth();

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <SiteHeader area="admin">
                {/* Zeile 2 (#90): alle Menüpunkte; auf dem Handy im Flyout-Menü (#259) */}
                <nav
                    aria-label="Administration"
                    className={cn(
                        'mx-auto hidden flex-wrap items-center gap-1 px-4 pb-2 text-sm desk:flex',
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
            <NewsQuestion />
            <UpdateNotice />
            <CookieNotice />
            <ConfirmHost />
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
