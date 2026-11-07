import type { SharedData } from '@/types';

type Auth = SharedData['auth'];

/** Recht des angemeldeten Kontos (nur für die Anzeige; geprüft wird serverseitig, #36). */
export function can(auth: Auth, permission: string): boolean {
    return auth.can[permission] === true;
}

/** Zugang zur Administration: irgendein Recht ausser «Vorschläge einreichen». */
export function hasAdminArea(auth: Auth): boolean {
    return Object.keys(auth.can).some((p) => p !== 'propose');
}
