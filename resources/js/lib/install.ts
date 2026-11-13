import { useSyncExternalStore } from 'react';

/**
 * Installation als WebApp (#240, #263): Das Ereignis `beforeinstallprompt` kommt nur einmal und früh; es wird beim
 * Start der App gemerkt, damit Hinweis-Balken und Flyout-Menü es später auslösen können.
 */
type InstallEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

let ereignis: InstallEvent | null = null;
let fertig = false;
const listeners = new Set<() => void>();
const melden = () => listeners.forEach((l) => l());

export function installationVorbereiten(): void {
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        ereignis = e as InstallEvent;
        melden();
    });
    window.addEventListener('appinstalled', () => {
        ereignis = null;
        fertig = true;
        melden();
    });
}

export const installiert = (): boolean =>
    fertig ||
    window.matchMedia('(display-mode: standalone)').matches ||
    (navigator as Navigator & { standalone?: boolean }).standalone === true;

export const ios = (): boolean =>
    /iphone|ipad|ipod/i.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

/** Installation direkt auslösbar (Android/Chrome, Edge)? */
export function useInstallierbar(): boolean {
    return useSyncExternalStore(
        (l) => {
            listeners.add(l);

            return () => listeners.delete(l);
        },
        () => ereignis !== null && !fertig,
        () => false,
    );
}

/** Startet die Installation; true, wenn angenommen. */
export async function installieren(): Promise<boolean> {
    if (!ereignis) {
        return false;
    }
    const e = ereignis;
    await e.prompt();
    const { outcome } = await e.userChoice;
    ereignis = null;
    melden();

    return outcome === 'accepted';
}
