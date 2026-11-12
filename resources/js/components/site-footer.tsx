import { Link, usePage } from '@inertiajs/react';
import { ChevronUp } from 'lucide-react';
import { useState } from 'react';
import BrandWordmark from '@/components/brand-wordmark';
import { Button } from '@/components/ui/button';
import { versionLabel, versionLabelShort } from '@/components/version-label';
import { useViewWidth, widthClass } from '@/hooks/use-view-width';
import { hasAdminArea } from '@/lib/access';
import { cn } from '@/lib/utils';

/**
 * Fusszeile mit Version, Links und Signatur (CI-Manual Abschnitt 9). Bleibt bei langen Listen sichtbar – auf der
 * Website (#28) und in der Administration (#102). Auf dem Handy eingeklappt: nur die Signatur und unten rechts ein
 * Ausklapp-Button für Version und Links (#226).
 */
export default function SiteFooter({ narrow }: { narrow: string }) {
    const { appVersion, auth, links } = usePage().props;
    const admin = hasAdminArea(auth);
    const { view } = useViewWidth();
    const [offen, setOffen] = useState(false);

    return (
        <footer className="sticky bottom-0 z-20 border-t border-border bg-background text-sm text-muted-foreground">
            <div
                className={cn(
                    'mx-auto flex flex-wrap items-center justify-between gap-x-3 gap-y-2 px-4 py-2 sm:py-4',
                    widthClass(view, narrow),
                )}
            >
                <span
                    id="fuss-links"
                    className={cn(
                        'w-full sm:inline sm:w-auto',
                        offen ? 'block' : 'hidden',
                    )}
                >
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
                        href="/entwickler"
                        className="text-link underline-offset-4 hover:underline"
                    >
                        Entwickler
                    </Link>
                    {' · '}
                    <Link
                        href={admin ? '/admin' : '/anmelden'}
                        className="text-link underline-offset-4 hover:underline"
                    >
                        Administration
                    </Link>
                    {' · '}
                    <Link
                        href="/hilfe"
                        className="text-link underline-offset-4 hover:underline"
                    >
                        Hilfe
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
                    {links.githubRepo ? (
                        <>
                            {' · '}
                            <a
                                href={`https://github.com/${links.githubRepo}/issues/new/choose`}
                                className="text-link underline-offset-4 hover:underline"
                            >
                                Fehler melden
                            </a>
                        </>
                    ) : null}
                    {links.pages ? (
                        <>
                            {' · '}
                            <a
                                href={links.pages}
                                className="text-link underline-offset-4 hover:underline"
                            >
                                GitHub Pages
                            </a>
                        </>
                    ) : null}
                </span>
                <div className="flex w-full items-center gap-2 sm:w-auto">
                    <a
                        href="https://markussx.ch/eduksx"
                        className="underline-offset-4 hover:underline"
                    >
                        Developed by <BrandWordmark brand="developer" />
                    </a>
                    {/* Handy: Version immer sichtbar (#233) */}
                    <Link
                        href="/versionen"
                        className="ml-auto text-link underline-offset-4 hover:underline sm:hidden"
                        title={`Version ${versionLabel(appVersion)}`}
                    >
                        {versionLabelShort(appVersion)}
                    </Link>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8 shrink-0 sm:hidden"
                        onClick={() => setOffen(!offen)}
                        aria-expanded={offen}
                        aria-controls="fuss-links"
                        aria-label={
                            offen
                                ? 'Version und Links ausblenden'
                                : 'Version und Links anzeigen'
                        }
                        title={offen ? 'Einklappen' : 'Ausklappen'}
                    >
                        <ChevronUp
                            aria-hidden
                            className={cn(
                                'transition-transform',
                                offen && 'rotate-180',
                            )}
                        />
                    </Button>
                </div>
            </div>
        </footer>
    );
}
