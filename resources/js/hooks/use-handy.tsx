import { useSyncExternalStore } from 'react';

/** Schmaler Bildschirm (unter 640 px, Tailwind `sm`): Handy-Darstellung der Startseite (#244–#246). */
const QUERY = '(max-width: 639px)';

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
