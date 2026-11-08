/** Einstellungen der Erweiterung (#153), in chrome.storage.sync (folgt dem Browser-Konto). */
export type Settings = {
    /** Ursprung des Glossary; Links und Index nur von dort. */
    origin: string;
    names: boolean;
    firstOnly: boolean;
    /** Ursprünge, auf denen die Erweiterung immer aktiv ist (mit erteilter Berechtigung). */
    sites: string[];
};

export const DEFAULTS: Settings = {
    origin: 'https://glossary.eduksx.ch',
    names: false,
    firstOnly: true,
    sites: [],
};

export async function getSettings(): Promise<Settings> {
    const stored = (await chrome.storage.sync.get('settings')) as {
        settings?: Partial<Settings>;
    };

    return { ...DEFAULTS, ...stored.settings };
}

export async function saveSettings(
    change: Partial<Settings>,
): Promise<Settings> {
    const next = { ...(await getSettings()), ...change };
    await chrome.storage.sync.set({ settings: next });

    return next;
}

/** Nur http(s)-Ursprünge ohne Pfad, z. B. https://www.schule.ch */
export function originOf(url: string | undefined): string | null {
    try {
        const u = new URL(url ?? '');

        return u.protocol === 'https:' || u.protocol === 'http:'
            ? u.origin
            : null;
    } catch {
        return null;
    }
}
