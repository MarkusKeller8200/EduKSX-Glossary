import { Link, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { versionLabel } from '@/components/version-label';

/**
 * Hinweis nach einem Update (#255): Hat sich der Stand seit dem letzten Besuch geändert, erscheint «Aktualisiert auf
 * Version …», bis er weggeklickt wird. Beim ersten Besuch wird der Stand nur gemerkt. Steht im Seitenfluss wie der
 * Cookie-Hinweis, in allen Ansichten und in der Administration.
 */
const KEY = 'glossary.version';

export default function UpdateNotice() {
    const { appVersion } = usePage().props;
    const stand = `${appVersion.version}|${appVersion.build ?? appVersion.build_date ?? ''}`;
    const [neu, setNeu] = useState(false);

    useEffect(() => {
        try {
            const alt = window.localStorage.getItem(KEY);
            if (alt === null) {
                window.localStorage.setItem(KEY, stand);
            } else if (alt !== stand) {
                setNeu(true);
            }
        } catch {
            // ohne Speicher kein Hinweis
        }
    }, [stand]);

    if (!neu) {
        return null;
    }

    const ok = () => {
        try {
            window.localStorage.setItem(KEY, stand);
        } catch {
            // ohne Speicher erscheint der Hinweis beim nächsten Laden wieder
        }
        setNeu(false);
    };

    return (
        <section
            aria-label="Hinweis zum Update"
            role="status"
            className="border-b border-border bg-success-soft text-sm"
        >
            <div className="mx-auto flex flex-wrap items-center gap-3 px-4 py-2">
                <Sparkles
                    className="size-4 shrink-0 text-brand-orange"
                    aria-hidden
                />
                <p className="min-w-0 flex-1">
                    Aktualisiert auf Version{' '}
                    <strong>{versionLabel(appVersion)}</strong>.{' '}
                    <Link
                        href="/versionen"
                        className="text-link underline underline-offset-4"
                        onClick={ok}
                    >
                        Was ist neu?
                    </Link>
                </p>
                <Button size="sm" variant="outline" onClick={ok}>
                    OK
                </Button>
            </div>
        </section>
    );
}
