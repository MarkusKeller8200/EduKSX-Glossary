import { palette } from '@/lib/colors';

/** Farbe des Vorschlagsstatus (#113): offen blau, Rückfrage orange, angenommen grün, sonst grau bzw. rot. */
export function proposalStatusClass(status: string): string {
    switch (status) {
        case 'organization_review':
            return palette.violet.badge;
        case 'submitted':
            return palette.sky.badge;
        case 'query':
            return palette.amber.badge;
        case 'accepted':
            return palette.emerald.badge;
        case 'rejected':
            return palette.rose.badge;
        default:
            return palette.slate.badge;
    }
}

/** Ereignistypen → Text. */
export const EREIGNIS: Record<string, string> = {
    submitted: 'Eingereicht',
    query: 'Rückfrage',
    reply: 'Antwort',
    accepted: 'Angenommen',
    rejected: 'Abgelehnt',
    withdrawn: 'Zurückgezogen',
    confirmed_by_organization: 'Von der Organisation bestätigt',
    rejected_by_organization: 'Von der Organisation abgelehnt',
    forwarded_by_organization: 'An die Redaktion weitergeleitet',
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
