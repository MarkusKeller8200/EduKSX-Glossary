import type { IndexResponse } from './types';

const KEY = 'gls-index-v1';

/** Höchstens einmal täglich neu laden; mit ETag meist nur 304. */
const MAX_AGE_MS = 24 * 60 * 60 * 1000;

type Cached = {
    origin: string;
    etag: string | null;
    at: number;
    data: IndexResponse;
};

/** Speicher (localStorage bzw. chrome.storage über einen Adapter); Fehler werden ignoriert. */
export type IndexStore = {
    get(key: string): Promise<string | null>;
    set(key: string, value: string): Promise<void>;
};

export const localStore: IndexStore = {
    async get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    async set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            // z. B. voller Speicher oder gesperrt: dann ohne Zwischenspeicher
        }
    },
};

/**
 * Kompakten Index laden (#159): aus dem Zwischenspeicher, wenn jünger als ein Tag; sonst mit If-None-Match
 * nachfragen. Ohne Netz bleibt der gespeicherte Stand.
 */
export async function loadIndex(
    origin: string,
    store: IndexStore = localStore,
    fetcher: typeof fetch = fetch,
): Promise<IndexResponse> {
    const base = origin.replace(/\/+$/, '');
    let cached: Cached | null = null;
    try {
        const raw = await store.get(KEY);
        cached = raw ? (JSON.parse(raw) as Cached) : null;
    } catch {
        cached = null;
    }
    if (cached !== null && cached.origin !== base) {
        cached = null;
    }
    if (cached !== null && Date.now() - cached.at < MAX_AGE_MS) {
        return cached.data;
    }
    try {
        const headers: Record<string, string> = { Accept: 'application/json' };
        if (cached?.etag) {
            headers['If-None-Match'] = cached.etag;
        }
        const response = await fetcher(`${base}/api/v1/index`, {
            headers,
            credentials: 'omit',
        });
        if (response.status === 304 && cached !== null) {
            await store.set(KEY, JSON.stringify({ ...cached, at: Date.now() }));

            return cached.data;
        }
        if (!response.ok) {
            throw new Error(
                `Glossary: Index nicht verfügbar (${response.status})`,
            );
        }
        const data = (await response.json()) as IndexResponse;
        await store.set(
            KEY,
            JSON.stringify({
                origin: base,
                etag: response.headers.get('ETag'),
                at: Date.now(),
                data,
            } satisfies Cached),
        );

        return data;
    } catch (error) {
        if (cached !== null) {
            return cached.data;
        }
        throw error;
    }
}
