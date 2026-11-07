import { Link } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * Cookie-Hinweis (#78): beim ersten Besuch, bis «Verstanden». Nur technisch nötige Cookies und selbst gewählte
 * Einstellungen – deshalb ein Hinweis, keine Einwilligung. Neue Fassung (VERSION erhöhen) → erscheint wieder.
 * Steht im Seitenfluss, verdeckt also keine Bedienelemente.
 */
const KEY = 'cookie-notice';
const VERSION = '1';
const listeners = new Set<() => void>();

const read = (): boolean => {
    try {
        return window.localStorage.getItem(KEY) !== VERSION;
    } catch {
        return true;
    }
};

let sichtbarJetzt: boolean | null = null;

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

export default function CookieNotice({
    className = '',
}: {
    className?: string;
}) {
    const sichtbar = useSyncExternalStore(
        subscribe,
        () => (sichtbarJetzt ??= read()),
        () => false,
    );

    if (!sichtbar) {
        return null;
    }

    const verstanden = () => {
        try {
            window.localStorage.setItem(KEY, VERSION);
        } catch {
            // ohne Speicher erscheint der Hinweis beim nächsten Besuch wieder
        }
        sichtbarJetzt = false;
        listeners.forEach((listener) => listener());
    };

    return (
        <section
            aria-label="Hinweis zu Cookies"
            className={cn(
                'border-b border-border bg-info-soft text-sm',
                className,
            )}
        >
            <div className="mx-auto flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <p>
                    Wir verwenden nur technisch nötige Cookies (Anmeldung,
                    Schutz gegen gefälschte Formulare) und speichern deine
                    Einstellungen (Hell/Dunkel, Ansicht) im Browser. Keine
                    Analyse, keine Werbung. Mehr in der{' '}
                    <Link
                        href="/datenschutz"
                        className="text-link underline underline-offset-4"
                    >
                        Datenschutzerklärung
                    </Link>
                    .
                </p>
                <Button size="sm" variant="outline" onClick={verstanden}>
                    Verstanden
                </Button>
            </div>
        </section>
    );
}
