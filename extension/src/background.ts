/**
 * Hintergrund der Browser-Erweiterung (#153, DEC-026): lädt den öffentlichen Index (einmal täglich, ETag) und
 * registriert das Inhaltsscript für die freigegebenen Websites. Es werden keine Seiteninhalte oder besuchten
 * Adressen übertragen oder gespeichert.
 */
import { loadIndex } from '../../packages/highlighter/src/loader';
import type { IndexStore } from '../../packages/highlighter/src/loader';
import { getSettings, originOf, saveSettings } from './settings';

const SCRIPT_ID = 'gls-content';

const store: IndexStore = {
    async get(key) {
        const value = (await chrome.storage.local.get(key)) as Record<
            string,
            string | undefined
        >;

        return value[key] ?? null;
    },
    async set(key, value) {
        await chrome.storage.local.set({ [key]: value });
    },
};

/** Inhaltsscript für alle freigegebenen Websites (immer aktiv) registrieren. */
async function syncSites(): Promise<void> {
    const { sites } = await getSettings();
    const registered = await chrome.scripting.getRegisteredContentScripts({
        ids: [SCRIPT_ID],
    });
    if (registered.length > 0) {
        await chrome.scripting.unregisterContentScripts({ ids: [SCRIPT_ID] });
    }
    const allowed: string[] = [];
    for (const site of sites) {
        if (await chrome.permissions.contains({ origins: [`${site}/*`] })) {
            allowed.push(`${site}/*`);
        }
    }
    if (allowed.length > 0) {
        await chrome.scripting.registerContentScripts([
            {
                id: SCRIPT_ID,
                matches: allowed,
                js: ['content.js'],
                runAt: 'document_idle',
                persistAcrossSessions: true,
            },
        ]);
    }
}

/** Erteilte bzw. entzogene Berechtigungen für Websites = «immer aktiv» (auch wenn das Menü dabei schliesst). */
async function updateSites(
    origins: string[] | undefined,
    add: boolean,
): Promise<void> {
    const changed = (origins ?? [])
        .map((o) => originOf(o.replace(/\/\*$/, '')))
        .filter((o): o is string => o !== null);
    if (changed.length === 0) {
        return;
    }
    const { sites } = await getSettings();
    await saveSettings({
        sites: add
            ? [...new Set([...sites, ...changed])]
            : sites.filter((s) => !changed.includes(s)),
    });
    await syncSites();
}

chrome.runtime.onInstalled.addListener(() => void syncSites());
chrome.runtime.onStartup.addListener(() => void syncSites());
chrome.permissions.onAdded.addListener(
    (p) => void updateSites(p.origins, true),
);
chrome.permissions.onRemoved.addListener(
    (p) => void updateSites(p.origins, false),
);

chrome.runtime.onMessage.addListener(
    (message: { type?: string }, sender, respond) => {
        // nur Nachrichten der eigenen Erweiterung
        if (sender.id !== chrome.runtime.id) {
            return false;
        }
        if (message.type === 'index') {
            void (async () => {
                const settings = await getSettings();
                try {
                    respond({
                        ok: true,
                        settings,
                        index: await loadIndex(settings.origin, store),
                    });
                } catch (error) {
                    respond({ ok: false, error: String(error) });
                }
            })();

            return true;
        }
        if (message.type === 'sync') {
            void syncSites().then(() => respond({ ok: true }));

            return true;
        }

        return false;
    },
);
