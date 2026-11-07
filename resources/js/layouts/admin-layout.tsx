import { Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import BrandWordmark from '@/components/brand-wordmark';
import { Button } from '@/components/ui/button';

/** Rahmen der Administration (nur angemeldet). Seitenleiste aus dem Intranet folgt mit der Redaktion. */
export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="border-b border-border bg-sidebar">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
                    <Link href="/admin" className="text-lg">
                        <BrandWordmark brand="education" suffix="Glossary" />
                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                            Administration
                        </span>
                    </Link>
                    <div className="flex items-center gap-3 text-sm">
                        <span className="text-muted-foreground">
                            {auth.user?.name}
                        </span>
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
            <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
                {children}
            </main>
        </div>
    );
}
