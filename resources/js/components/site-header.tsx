import { Link, router, usePage } from '@inertiajs/react';
import { LogIn, LogOut, UserRound } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import AppearanceToggleTab from '@/components/appearance-tabs';
import BrandWordmark from '@/components/brand-wordmark';
import GlossaryIcon from '@/components/glossary-icon';
import MobileMenu from '@/components/mobile-menu';
import { Button } from '@/components/ui/button';
import ViewWidthTabs from '@/components/view-width-tabs';
import { useViewWidth, widthClass } from '@/hooks/use-view-width';
import { hasAdminArea } from '@/lib/access';
import { cn } from '@/lib/utils';

/** Rollen (Schlüssel) → Bezeichnung; angezeigt wird die höchste (#146). */
const ROLLEN: [string, string][] = [
    ['developer', 'Developer'],
    ['admin', 'Admin'],
    ['moderator', 'Moderator'],
    ['org-admin', 'Organisations-Admin'],
    ['benutzer', 'Benutzer'],
    ['gast', 'Gast'],
];

/**
 * Kopfzeile für Website und Administration, überall gleich (#146): Titel, Wechsel Website/Administration, Ansicht,
 * Farbschema, angemeldete Person mit Rolle bzw. «Gast», Anmelden/Abmelden. Die Administration ergänzt darunter ihr
 * Menü (`children`). Auf dem Handy (#227, #259) eine Zeile: Flyout-Menü, Titel, Person und Ab-/Anmelde-Icon;
 * Farbschema und Wechsel stehen im Flyout-Menü.
 */
export default function SiteHeader({
    area,
    children,
}: {
    area: 'website' | 'admin';
    children?: ReactNode;
}) {
    const { auth } = usePage().props;
    const { view } = useViewWidth();
    const admin = hasAdminArea(auth);
    const rolle = auth.user
        ? (ROLLEN.find(([key]) => auth.roles.includes(key))?.[1] ??
          'ohne Rolle')
        : 'Gast';

    const kopf = useRef<HTMLElement>(null);

    // Höhe als CSS-Variable --kopf-hoehe, damit fixierte Leisten darunter stehen (#238)
    useEffect(() => {
        const el = kopf.current;
        if (!el) {
            return;
        }
        const setzen = () =>
            document.documentElement.style.setProperty(
                '--kopf-hoehe',
                `${el.offsetHeight}px`,
            );
        setzen();
        const ro = new ResizeObserver(setzen);
        ro.observe(el);

        return () => ro.disconnect();
    }, []);

    return (
        <header
            ref={kopf}
            className="sticky top-0 z-20 border-b border-border bg-background"
        >
            <div
                className={cn(
                    'mx-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3',
                    widthClass(view, 'max-w-6xl'),
                )}
            >
                {/* Handy (#259): Flyout-Menü links */}
                <div className="flex min-w-0 items-center gap-1">
                    <div className="desk:hidden">
                        <MobileMenu area={area} rolle={rolle} />
                    </div>
                    <Link
                        href={area === 'admin' ? '/admin' : '/'}
                        className="flex shrink-0 items-center gap-2 rounded-sm text-lg focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none desk:text-xl"
                    >
                        <GlossaryIcon className="size-6 desk:size-7" />
                        <BrandWordmark brand="education" suffix="Glossary" />
                        {area === 'admin' ? (
                            <span className="ml-2 hidden text-sm font-normal text-muted-foreground desk:inline">
                                Administration
                            </span>
                        ) : null}
                    </Link>
                </div>

                {/* Handy (#227): Person, Ab-/Anmelden und Ausklappen auf der Titelzeile */}
                <div className="flex min-w-0 flex-1 items-center justify-end gap-1 text-sm desk:hidden">
                    {auth.user ? (
                        <>
                            <Link
                                href="/konto"
                                className="flex min-w-0 items-center gap-1.5 rounded-md px-2 py-1 hover:bg-accent"
                                title={`Mein Konto (${rolle})`}
                            >
                                <UserRound
                                    className="size-4 shrink-0"
                                    aria-hidden
                                />
                                <span className="truncate">
                                    {auth.user.name}
                                </span>
                            </Link>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 shrink-0"
                                onClick={() => router.post('/abmelden')}
                                aria-label="Abmelden"
                                title="Abmelden"
                            >
                                <LogOut aria-hidden />
                            </Button>
                        </>
                    ) : (
                        <>
                            <span className="flex items-center gap-1.5 px-2 py-1 text-muted-foreground">
                                <UserRound className="size-4" aria-hidden />
                                Gast
                            </span>
                            <Button
                                asChild
                                variant="ghost"
                                size="icon"
                                className="size-8 shrink-0"
                            >
                                <Link
                                    href="/anmelden"
                                    aria-label="Anmelden"
                                    title="Anmelden"
                                >
                                    <LogIn aria-hidden />
                                </Link>
                            </Button>
                        </>
                    )}
                </div>

                <div
                    id="kopf-schaltflaechen"
                    className="hidden flex-wrap items-center gap-2 text-sm desk:flex"
                >
                    {area === 'admin' ? (
                        <Button asChild size="sm" variant="ghost">
                            <Link href="/">Website</Link>
                        </Button>
                    ) : admin ? (
                        <Button asChild size="sm" variant="ghost">
                            <Link href="/admin">Administration</Link>
                        </Button>
                    ) : null}
                    {/* Ansichtsbreite auf dem Handy nicht nötig (#241) */}
                    <div className="hidden desk:block">
                        <ViewWidthTabs />
                    </div>
                    <AppearanceToggleTab />
                    {auth.user ? (
                        <>
                            <Link
                                href="/konto"
                                className="hidden items-center gap-1.5 rounded-md px-2 py-1 hover:bg-accent desk:flex"
                                title="Mein Konto"
                            >
                                <UserRound className="size-4" aria-hidden />
                                <span>{auth.user.name}</span>
                                <span className="text-xs text-muted-foreground">
                                    ({rolle})
                                </span>
                            </Link>
                            <Button
                                variant="outline"
                                size="sm"
                                className="hidden desk:inline-flex"
                                onClick={() => router.post('/abmelden')}
                            >
                                <LogOut aria-hidden /> Abmelden
                            </Button>
                        </>
                    ) : (
                        <>
                            <span className="hidden items-center gap-1.5 px-2 py-1 text-muted-foreground desk:flex">
                                <UserRound className="size-4" aria-hidden />
                                Gast
                            </span>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="hidden desk:inline-flex"
                            >
                                <Link href="/anmelden">
                                    <LogIn aria-hidden /> Anmelden
                                </Link>
                            </Button>
                        </>
                    )}
                </div>
            </div>
            {children}
        </header>
    );
}
