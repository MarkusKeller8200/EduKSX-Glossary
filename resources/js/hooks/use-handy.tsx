import { useSyncExternalStore } from 'react';

/**
 * Handy-Darstellung (#244–#246, #253): unter 640 px Breite oder Handy quer (grobe Bedienung, höchstens 500 px hoch).
 * Gegenstück zur Variante `desk:` in resources/css/app.css.
 */
export const HANDY_QUERY =
    '(max-width: 639px), (pointer: coarse) and (max-height: 500px)';
const QUERY = HANDY_QUERY;

const subscribe = (callback: () => void) => {
    const mq = window.matchMedia(QUERY);
    mq.addEventListener('change', callback);

    return () => mq.removeEventListener('change', callback);
};

export function useHandy(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => window.matchMedia(QUERY).matches,
        () => false,
    );
}
