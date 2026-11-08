import { useSyncExternalStore } from 'react';

/**
 * Ansicht wählen (#76, #144): Mobil (schmale Spalte wie auf dem Handy), Darstellungsoptimiert (1152 px, Schlüssel
 * «narrow»), Breit (1536 px) oder Voll (ganze Fensterbreite). Gespeichert nur im Browser (localStorage «view-width»,
 * ohne Personenbezug), gilt für Website und Administration.
 */
export type ViewWidth = 'mobile' | 'narrow' | 'wide' | 'full';

const KEY = 'view-width';
const listeners = new Set<() => void>();

const read = (): ViewWidth => {
    if (typeof window === 'undefined') {
        return 'narrow';
    }
    try {
        const value = window.localStorage.getItem(KEY);

        return value === 'mobile' || value === 'wide' || value === 'full'
            ? value
            : 'narrow';
    } catch {
        return 'narrow';
    }
};

let current: ViewWidth = read();

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

/** Breite des Inhalts: Mobil schmal und zentriert, sonst darstellungsoptimiert, breit bzw. volle Breite. */
export function widthClass(view: ViewWidth, narrow: string): string {
    switch (view) {
        case 'mobile':
            return 'max-w-sm';
        case 'wide':
            return 'max-w-[96rem]';
        case 'full':
            return 'max-w-none';
        default:
            return narrow;
    }
}

/**
 * Seiten, die darstellungsoptimiert gestaltet sind (Formulare, Detailseite eines Begriffs, Konto). Bei «Breit»
 * und «Voll» bleiben sie auf der optimierten Breite und werden zentriert (#144); Listen und Tabellen nutzen die
 * gewählte Breite.
 */
const OPTIMIZED = [
    'term',
    'account/',
    'auth/',
    'proposals/create',
    'proposals/show',
    'admin/term-form',
    'admin/user-show',
    'admin/service-account-show',
    'admin/settings',
    'admin/import',
];

export function isOptimizedPage(component: string): boolean {
    return OPTIMIZED.some((p) =>
        p.endsWith('/') ? component.startsWith(p) : component === p,
    );
}

/** Breite des Hauptinhalts für eine Seite: optimierte Seiten nie breiter als `narrow`, immer zentriert. */
export function pageWidthClass(
    view: ViewWidth,
    narrow: string,
    component: string,
): string {
    if (view !== 'mobile' && isOptimizedPage(component)) {
        return narrow;
    }

    return widthClass(view, narrow);
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
