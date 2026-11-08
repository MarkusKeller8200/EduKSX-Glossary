import { palette } from '@/lib/colors';

/** Farbe des Vorschlagsstatus (#113): offen blau, Rückfrage orange, angenommen grün, sonst grau bzw. rot. */
export function proposalStatusClass(status: string): string {
    switch (status) {
        case 'org_pruefung':
            return palette.violet.badge;
        case 'eingereicht':
            return palette.sky.badge;
        case 'rueckfrage':
            return palette.amber.badge;
        case 'angenommen':
            return palette.emerald.badge;
        case 'abgelehnt':
            return palette.rose.badge;
        default:
            return palette.slate.badge;
    }
}

/** Ereignistypen → Text. */
export const EREIGNIS: Record<string, string> = {
    eingereicht: 'Eingereicht',
    rueckfrage: 'Rückfrage',
    antwort: 'Antwort',
    angenommen: 'Angenommen',
    abgelehnt: 'Abgelehnt',
    zurueckgezogen: 'Zurückgezogen',
    org_bestaetigt: 'Von der Organisation bestätigt',
    org_abgelehnt: 'Von der Organisation abgelehnt',
    org_weitergeleitet: 'An die Redaktion weitergeleitet',
};

/** Wert eines Feldes zur Anzeige (Listen mit Strichpunkt, leer als «–»). */
export function wertText(
    value: unknown,
    labels: Record<string, string> = {},
): string {
    if (Array.isArray(value)) {
        return value.length
            ? value.map((v) => labels[String(v)] ?? String(v)).join('; ')
            : '–';
    }
    const s =
        typeof value === 'string' || typeof value === 'number'
            ? String(value)
            : value === null || value === undefined
              ? ''
              : JSON.stringify(value);

    return s.trim() === '' ? '–' : s;
}
