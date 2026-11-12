import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import ConfirmHost from '@/components/confirm-dialog';
import CookieNotice from '@/components/cookie-notice';
import UpdateNotice from '@/components/update-notice';
import InstallApp from '@/components/install-app';
import SiteFooter from '@/components/site-footer';
import NewsQuestion from '@/components/news-question';
import SiteHeader from '@/components/site-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    pageWidthClass,
    useViewWidth,
    widthClass,
} from '@/hooks/use-view-width';
import { cn } from '@/lib/utils';

/** Seiten, die die Meldung selbst an passender Stelle zeigen. */
const EIGENE_MELDUNG = ['term', 'auth/login', 'auth/forgot-password'];

/**
 * Öffentlicher Rahmen: Kopf (gleich wie in der Administration, #146), Inhalt, Fuss mit Version und Signatur
 * (CI-Manual Abschnitt 9). Kopf und Fuss bleiben bei langen Listen sichtbar (#28).
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    const { appVersion, flash } = usePage().props;
    const { component } = usePage();
    const { view } = useViewWidth();

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <SiteHeader area="website" />
            <NewsQuestion />
            <UpdateNotice />
            <CookieNotice />
            <InstallApp />
            <ConfirmHost />

            {appVersion.prototype ? (
                <div
                    role="note"
                    className="bg-secondary text-secondary-foreground"
                >
                    <p
                        className={cn(
                            'mx-auto px-4 py-2 text-sm',
                            widthClass(view, 'max-w-6xl'),
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
                    pageWidthClass(view, 'max-w-6xl', component),
                )}
            >
                {/* Meldungen einheitlich oben (#139); Seiten mit eigener Anzeige ausgenommen */}
                {flash.status && !EIGENE_MELDUNG.includes(component) ? (
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
