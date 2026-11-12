import { Link, router, usePage } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { LucideIcon } from 'lucide-react';
import {
    BookOpenText,
    Code2,
    Globe,
    History,
    LifeBuoy,
    LogIn,
    LogOut,
    Menu,
    ShieldCheck,
    UserRound,
    X,
} from 'lucide-react';
import { useState } from 'react';
import AppearanceToggleTab from '@/components/appearance-tabs';
import BrandWordmark from '@/components/brand-wordmark';
import GlossaryIcon from '@/components/glossary-icon';
import { Button } from '@/components/ui/button';
import { can, hasAdminArea } from '@/lib/access';
import { ADMIN_NAV } from '@/lib/admin-nav';
import { cn } from '@/lib/utils';

/** Menüpunkte der Website im Flyout-Menü. */
const WEBSITE: { href: string; label: string; icon: LucideIcon }[] = [
    { href: '/', label: 'Begriffe', icon: BookOpenText },
    { href: '/hilfe', label: 'Hilfe', icon: LifeBuoy },
    { href: '/entwickler', label: 'Entwickler', icon: Code2 },
    { href: '/versionen', label: 'Versionen', icon: History },
];

/**
 * Flyout-Menü links für das Handy, hoch und quer (#259): angemeldete Person mit Ab-/Anmelden, Wechsel zwischen
 * Website und Administration, die Menüpunkte (in der Administration alle mit Recht) und das Farbschema.
 */
export default function MobileMenu({
    area,
    rolle,
}: {
    area: 'website' | 'admin';
    rolle: string;
}) {
    const { auth } = usePage().props;
    const path = usePage().url.split('?')[0];
    const [offen, setOffen] = useState(false);
    const admin = hasAdminArea(auth);
    const zu = () => setOffen(false);

    const eintrag = (
        href: string,
        label: string,
        Icon: LucideIcon,
        aktiv: boolean,
    ) => (
        <Link
            key={href}
            href={href}
            onClick={zu}
            aria-current={aktiv ? 'page' : undefined}
            className={cn(
                'flex items-center gap-3 rounded-md px-3 py-2 hover:bg-accent hover:text-accent-foreground',
                aktiv && 'bg-secondary font-medium text-secondary-foreground',
            )}
        >
            <Icon className="size-4 shrink-0" aria-hidden />
            {label}
        </Link>
    );

    return (
        <DialogPrimitive.Root open={offen} onOpenChange={setOffen}>
            <DialogPrimitive.Trigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="-ml-2 size-9 shrink-0"
                    aria-label="Menü öffnen"
                    title="Menü"
                >
                    <Menu aria-hidden />
                </Button>
            </DialogPrimitive.Trigger>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/50 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
                <DialogPrimitive.Content className="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] flex-col border-r border-border bg-background shadow-lg data-[state=closed]:animate-out data-[state=closed]:slide-out-to-left data-[state=open]:animate-in data-[state=open]:slide-in-from-left">
                    <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                        <GlossaryIcon />
                        <DialogPrimitive.Title className="min-w-0 flex-1 text-lg">
                            <BrandWordmark
                                brand="education"
                                suffix="Glossary"
                            />
                        </DialogPrimitive.Title>
                        <DialogPrimitive.Description className="sr-only">
                            Menü mit Anmeldung, Bereichen und Menüpunkten
                        </DialogPrimitive.Description>
                        <DialogPrimitive.Close asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                aria-label="Menü schliessen"
                            >
                                <X aria-hidden />
                            </Button>
                        </DialogPrimitive.Close>
                    </div>

                    <nav
                        aria-label="Menü"
                        className="flex-1 overflow-y-auto px-2 py-3 text-sm"
                    >
                        {/* Person mit Ab-/Anmelden */}
                        <div className="flex items-center gap-2 px-1 pb-2">
                            {auth.user ? (
                                <>
                                    <Link
                                        href="/konto"
                                        onClick={zu}
                                        className="flex min-w-0 flex-1 items-center gap-3 rounded-md px-2 py-2 hover:bg-accent"
                                        title="Mein Konto"
                                    >
                                        <UserRound
                                            className="size-4 shrink-0"
                                            aria-hidden
                                        />
                                        <span className="min-w-0">
                                            <span className="block truncate font-medium">
                                                {auth.user.name}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {rolle}
                                            </span>
                                        </span>
                                    </Link>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="size-9 shrink-0"
                                        onClick={() => {
                                            zu();
                                            router.post('/abmelden');
                                        }}
                                        aria-label="Abmelden"
                                        title="Abmelden"
                                    >
                                        <LogOut aria-hidden />
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <span className="flex flex-1 items-center gap-3 px-2 py-2 text-muted-foreground">
                                        <UserRound
                                            className="size-4"
                                            aria-hidden
                                        />
                                        Gast
                                    </span>
                                    <Button asChild variant="outline" size="sm">
                                        <Link href="/anmelden" onClick={zu}>
                                            <LogIn aria-hidden /> Anmelden
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </div>

                        {/* Wechsel Website / Administration */}
                        {area === 'admin'
                            ? eintrag('/', 'Website', Globe, false)
                            : admin
                              ? eintrag(
                                    '/admin',
                                    'Administration',
                                    ShieldCheck,
                                    false,
                                )
                              : null}

                        <div className="my-2 border-t border-border" />

                        {area === 'admin'
                            ? ADMIN_NAV.filter(
                                  (i) => i.recht === null || can(auth, i.recht),
                              ).map((i) =>
                                  eintrag(
                                      i.href,
                                      i.label,
                                      i.icon,
                                      path === i.href,
                                  ),
                              )
                            : WEBSITE.map((i) =>
                                  eintrag(
                                      i.href,
                                      i.label,
                                      i.icon,
                                      path === i.href,
                                  ),
                              )}
                    </nav>

                    <div className="border-t border-border px-4 py-3">
                        <AppearanceToggleTab />
                    </div>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
