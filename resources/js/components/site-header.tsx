import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, LogIn, LogOut, UserRound } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import AppearanceToggleTab from '@/components/appearance-tabs';
import BrandWordmark from '@/components/brand-wordmark';
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
 * Menü (`children`). Auf dem Handy (#227) eine Zeile: Titel, Person, Ab-/Anmelde-Icon und ein Ausklapp-Button für
 * die übrigen Schaltflächen.
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

    const [offen, setOffen] = useState(false);

    return (
        <header className="sticky top-0 z-20 border-b border-border bg-background">
            <div
                className={cn(
                    'mx-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3',
                    widthClass(view, 'max-w-6xl'),
                )}
            >
                <Link
                    href={area === 'admin' ? '/admin' : '/'}
                    className="shrink-0 rounded-sm text-lg focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:text-xl"
                >
                    <BrandWordmark brand="education" suffix="Glossary" />
                    {area === 'admin' ? (
                        <span className="ml-2 hidden text-sm font-normal text-muted-foreground sm:inline">
                            Administration
                        </span>
                    ) : null}
                </Link>

                {/* Handy (#227): Person, Ab-/Anmelden und Ausklappen auf der Titelzeile */}
                <div className="flex min-w-0 flex-1 items-center justify-end gap-1 text-sm sm:hidden">
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
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8 shrink-0"
                        onClick={() => setOffen(!offen)}
                        aria-expanded={offen}
                        aria-controls="kopf-schaltflaechen"
                        aria-label={
                            offen
                                ? 'Weitere Schaltflächen ausblenden'
                                : 'Weitere Schaltflächen anzeigen'
                        }
                        title={offen ? 'Einklappen' : 'Ausklappen'}
                    >
                        <ChevronDown
                            aria-hidden
                            className={cn(
                                'transition-transform',
                                offen && 'rotate-180',
                            )}
                        />
                    </Button>
                </div>

                <div
                    id="kopf-schaltflaechen"
                    className={cn(
                        'w-full flex-wrap items-center gap-2 text-sm sm:flex sm:w-auto',
                        offen ? 'flex' : 'hidden',
                    )}
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
                    <ViewWidthTabs />
                    <AppearanceToggleTab />
                    {auth.user ? (
                        <>
                            <Link
                                href="/konto"
                                className="hidden items-center gap-1.5 rounded-md px-2 py-1 hover:bg-accent sm:flex"
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
                                className="hidden sm:inline-flex"
                                onClick={() => router.post('/abmelden')}
                            >
                                <LogOut aria-hidden /> Abmelden
                            </Button>
                        </>
                    ) : (
                        <>
                            <span className="hidden items-center gap-1.5 px-2 py-1 text-muted-foreground sm:flex">
                                <UserRound className="size-4" aria-hidden />
                                Gast
                            </span>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="hidden sm:inline-flex"
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
