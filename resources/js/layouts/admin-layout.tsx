import { Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import BrandWordmark from '@/components/brand-wordmark';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const NAV = [
    { href: '/admin', label: 'Übersicht' },
    { href: '/admin/begriffe', label: 'Begriffe' },
    { href: '/admin/listen', label: 'Listen' },
    { href: '/admin/import', label: 'Excel-Import' },
];

/** Rahmen der Administration (nur angemeldet). Seitenleiste aus dem Intranet folgt mit der Redaktion (#14). */
export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth, flash } = usePage().props;
    const path = usePage().url.split('?')[0];

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="border-b border-border bg-sidebar">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-3">
                    <Link href="/admin" className="text-lg">
                        <BrandWordmark brand="education" suffix="Glossary" />
                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                            Administration
                        </span>
                    </Link>
                    <nav
                        aria-label="Administration"
                        className="flex items-center gap-1 text-sm"
                    >
                        {NAV.map((item) => (
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
                    <div className="flex items-center gap-3 text-sm">
                        <Link
                            href="/"
                            className="text-link underline-offset-4 hover:underline"
                        >
                            Website
                        </Link>
                        <Link
                            href="/admin/zwei-faktor"
                            className="text-muted-foreground underline-offset-4 hover:underline"
                            title="Zweiter Faktor"
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
            </header>
            <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
                {flash.status ? (
                    <Alert className="mb-6" role="status">
                        <AlertDescription>{flash.status}</AlertDescription>
                    </Alert>
                ) : null}
                {children}
            </main>
        </div>
    );
}
