import { Link, usePage } from '@inertiajs/react';
import BrandWordmark from '@/components/brand-wordmark';
import { versionLabel } from '@/components/version-label';
import { useViewWidth, widthClass } from '@/hooks/use-view-width';
import { hasAdminArea } from '@/lib/access';
import { cn } from '@/lib/utils';

/**
 * Fusszeile mit Version, Links und Signatur (CI-Manual Abschnitt 9). Bleibt bei langen Listen sichtbar – auf der
 * Website (#28) und in der Administration (#102).
 */
export default function SiteFooter({ narrow }: { narrow: string }) {
    const { appVersion, auth, links } = usePage().props;
    const admin = hasAdminArea(auth);
    const { view } = useViewWidth();

    return (
        <footer className="sticky bottom-0 z-20 border-t border-border bg-background text-sm text-muted-foreground">
            <div
                className={cn(
                    'mx-auto flex flex-wrap items-center justify-between gap-3 px-4 py-4',
                    widthClass(view, narrow),
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
    );
}
