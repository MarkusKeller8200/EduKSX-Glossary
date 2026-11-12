import { Download, Share, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { HANDY_QUERY } from '@/hooks/use-handy';
import {
    installieren,
    installiert,
    ios,
    useInstallierbar,
} from '@/lib/install';

/**
 * Hinweis «App installieren» auf dem Handy (#240). Android/Chrome: Installation über das Ereignis
 * `beforeinstallprompt`; iPhone/iPad (Safari kennt das Ereignis nicht): kurze Anleitung «Teilen → Zum
 * Home-Bildschirm». Nicht in der installierten App, nicht am Desktop; «Schliessen» blendet ihn dauerhaft aus.
 */
const KEY = 'glossary.app-hinweis';

const handy = (): boolean =>
    window.matchMedia('(pointer: coarse)').matches &&
    window.matchMedia(HANDY_QUERY).matches;

const geschlossen = (): boolean => {
    try {
        return window.localStorage.getItem(KEY) === 'aus';
    } catch {
        return false;
    }
};

export default function InstallApp() {
    const kann = useInstallierbar();
    const [art, setArt] = useState<'android' | 'ios' | null>(null);

    useEffect(() => {
        if (!handy() || installiert() || geschlossen()) {
            setArt(null);

            return;
        }
        setArt(ios() ? 'ios' : kann ? 'android' : null);
    }, [kann]);

    if (art === null) {
        return null;
    }

    const schliessen = () => {
        try {
            window.localStorage.setItem(KEY, 'aus');
        } catch {
            // ohne Speicher erscheint der Hinweis beim nächsten Besuch wieder
        }
        setArt(null);
    };

    const installierenKlick = async () => {
        if (await installieren()) {
            setArt(null);
        }
    };

    return (
        <section
            aria-label="App installieren"
            className="border-b border-border bg-info-soft text-sm"
        >
            <div className="mx-auto flex items-center gap-3 px-4 py-2">
                <img
                    src="/icon-192.png"
                    alt=""
                    className="size-8 shrink-0 rounded-md"
                />
                <p className="min-w-0 flex-1">
                    {art === 'ios' ? (
                        <>
                            Als App nutzen:{' '}
                            <Share
                                className="inline size-4 align-text-bottom"
                                aria-label="Teilen"
                            />{' '}
                            antippen, dann «Zum Home-Bildschirm».
                        </>
                    ) : (
                        'Das Glossar als App auf dem Home-Bildschirm.'
                    )}
                </p>
                {art === 'android' ? (
                    <Button size="sm" onClick={() => void installierenKlick()}>
                        <Download aria-hidden /> Installieren
                    </Button>
                ) : null}
                <Button
                    size="icon"
                    variant="ghost"
                    className="size-8 shrink-0"
                    onClick={schliessen}
                    aria-label="Hinweis schliessen"
                    title="Schliessen"
                >
                    <X aria-hidden />
                </Button>
            </div>
        </section>
    );
}
