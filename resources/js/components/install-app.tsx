import { Download, Share, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { HANDY_QUERY } from '@/hooks/use-handy';

/**
 * Hinweis «App installieren» auf dem Handy (#240). Android/Chrome: Installation über das Ereignis
 * `beforeinstallprompt`; iPhone/iPad (Safari kennt das Ereignis nicht): kurze Anleitung «Teilen → Zum
 * Home-Bildschirm». Nicht in der installierten App, nicht am Desktop; «Schliessen» blendet ihn dauerhaft aus.
 */
const KEY = 'glossary.app-hinweis';

type InstallEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

const handy = (): boolean =>
    window.matchMedia('(pointer: coarse)').matches &&
    window.matchMedia(HANDY_QUERY).matches;

const installiert = (): boolean =>
    window.matchMedia('(display-mode: standalone)').matches ||
    (navigator as Navigator & { standalone?: boolean }).standalone === true;

const ios = (): boolean =>
    /iphone|ipad|ipod/i.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

const geschlossen = (): boolean => {
    try {
        return window.localStorage.getItem(KEY) === 'aus';
    } catch {
        return false;
    }
};

export default function InstallApp() {
    const [ereignis, setEreignis] = useState<InstallEvent | null>(null);
    const [art, setArt] = useState<'android' | 'ios' | null>(null);

    useEffect(() => {
        if (!handy() || installiert() || geschlossen()) {
            return;
        }
        if (ios()) {
            setArt('ios');

            return;
        }
        const merken = (e: Event) => {
            e.preventDefault();
            setEreignis(e as InstallEvent);
            setArt('android');
        };
        const fertig = () => setArt(null);
        window.addEventListener('beforeinstallprompt', merken);
        window.addEventListener('appinstalled', fertig);

        return () => {
            window.removeEventListener('beforeinstallprompt', merken);
            window.removeEventListener('appinstalled', fertig);
        };
    }, []);

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

    const installieren = async () => {
        if (!ereignis) {
            return;
        }
        await ereignis.prompt();
        const { outcome } = await ereignis.userChoice;
        setEreignis(null);
        if (outcome === 'accepted') {
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
                    <Button size="sm" onClick={() => void installieren()}>
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
