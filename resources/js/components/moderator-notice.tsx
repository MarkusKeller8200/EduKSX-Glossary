import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useViewWidth, widthClass } from '@/hooks/use-view-width';
import { cn } from '@/lib/utils';

/**
 * Einmaliger Hinweis nach der Anmeldung (#460): Mithelfen als Moderator/in. Der Server vermerkt ihn beim Anzeigen als
 * gesehen – er erscheint also nur dieses eine Mal.
 */
export default function ModeratorNotice() {
    const { view } = useViewWidth();
    const { moderatorHinweis } = usePage().props;
    const [zu, setZu] = useState(false);
    if (!moderatorHinweis || zu) {
        return null;
    }

    return (
        <div
            role="region"
            aria-label="Mithelfen als Moderator/in"
            className="border-b border-border bg-amber-50 text-amber-950 dark:bg-amber-950 dark:text-amber-50"
        >
            <div
                className={cn(
                    'mx-auto flex flex-wrap items-center gap-3 px-4 py-2 text-sm',
                    widthClass(view, 'max-w-6xl'),
                )}
            >
                <p className="flex-1">
                    <strong>Mithelfen als Moderator/in?</strong> Prüfen Sie
                    Vorschläge der Community und pflegen Sie Begriffe.
                    {moderatorHinweis.fehlt.length > 0
                        ? ` Voraussetzung: ${moderatorHinweis.fehlt.join(' und ')}.`
                        : ''}{' '}
                    <span className="text-xs opacity-80">
                        Diese Mitteilung wird nur einmal angezeigt; bewerben
                        können Sie sich jederzeit unter «Mein Konto».
                    </span>
                </p>
                <Button size="sm" asChild>
                    <Link href="/konto#moderation">Mehr erfahren</Link>
                </Button>
                <Button size="sm" variant="outline" onClick={() => setZu(true)}>
                    Schliessen
                </Button>
            </div>
        </div>
    );
}
