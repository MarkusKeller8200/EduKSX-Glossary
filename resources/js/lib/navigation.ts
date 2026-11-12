import { router } from '@inertiajs/react';

/**
 * Zählt Seitenwechsel innerhalb der App (#250): «Schliessen» in der Detailansicht geht nur dann mit dem Browser
 * zurück, wenn die vorherige Seite aus dieser App stammt; sonst (z. B. Permalink von aussen) zur Startseite.
 */
let wechsel = 0;

export function navigationZaehlen(): void {
    router.on('navigate', () => {
        wechsel += 1;
    });
}

export function zurueckOderStart(): void {
    if (wechsel > 1) {
        window.history.back();
    } else {
        router.visit('/');
    }
}
