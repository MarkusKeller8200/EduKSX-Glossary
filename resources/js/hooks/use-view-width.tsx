import { useSyncExternalStore } from 'react';

/**
 * Ansicht wählen (#76): Mobil (schmale Spalte wie auf dem Handy), Desktop schmal (begrenzte Breite) oder ganze
 * Breite. Gespeichert nur im Browser (localStorage «view-width», ohne Personenbezug), gilt für Website und
 * Administration.
 */
export type ViewWidth = 'mobile' | 'narrow' | 'full';

const KEY = 'view-width';
const listeners = new Set<() => void>();

const read = (): ViewWidth => {
    if (typeof window === 'undefined') {
        return 'narrow';
    }
    try {
        const value = window.localStorage.getItem(KEY);

        return value === 'mobile' || value === 'full' ? value : 'narrow';
    } catch {
        return 'narrow';
    }
};

let current: ViewWidth = read();

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

/** Breite des Inhalts: Mobil schmal und zentriert, sonst die übliche bzw. volle Breite. */
export function widthClass(view: ViewWidth, narrow: string): string {
    switch (view) {
        case 'mobile':
            return 'max-w-sm';
        case 'full':
            return 'max-w-none';
        default:
            return narrow;
    }
}

export function useViewWidth(): {
    view: ViewWidth;
    setView: (view: ViewWidth) => void;
} {
    const view = useSyncExternalStore(
        subscribe,
        () => current,
        () => 'narrow' as ViewWidth,
    );
    const setView = (next: ViewWidth): void => {
        current = next;
        try {
            window.localStorage.setItem(KEY, next);
        } catch {
            // ohne Speicher gilt die Wahl nur bis zum Neuladen
        }
        listeners.forEach((listener) => listener());
    };

    return { view, setView };
}
