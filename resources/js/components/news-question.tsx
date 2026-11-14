import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useViewWidth, widthClass } from '@/hooks/use-view-width';
import { cn } from '@/lib/utils';

/**
 * Einmalige Frage nach der Anmeldung (#169): News zum Glossary erhalten? Keine Werbung, freiwillig, jederzeit unter
 * «Mein Konto» änderbar. Erscheint, bis eine Antwort gespeichert ist.
 */
export default function NewsQuestion() {
    const { view } = useViewWidth();
    const { auth } = usePage().props;
    if (!auth.newsFrage) {
        return null;
    }
    const antworten = (news: boolean) =>
        router.post('/konto/news', { news }, { preserveScroll: true });

    return (
        <div
            role="region"
            aria-label="News zum Glossary"
            className="border-b border-border bg-sky-50 text-sky-950 dark:bg-sky-950 dark:text-sky-50"
        >
            <div
                className={cn(
                    'mx-auto flex flex-wrap items-center gap-3 px-4 py-2 text-sm',
                    widthClass(view, 'max-w-6xl'),
                )}
            >
                <p className="flex-1">
                    Möchten Sie gelegentlich News zum Glossary per E-Mail
                    erhalten (neue Funktionen, wichtige Änderungen)? Keine
                    Werbung, jederzeit abbestellbar.
                </p>
                <Button size="sm" onClick={() => antworten(true)}>
                    Ja, gerne
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => antworten(false)}
                >
                    Nein danke
                </Button>
            </div>
        </div>
    );
}
