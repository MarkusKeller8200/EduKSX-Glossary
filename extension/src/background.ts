/**
 * Hintergrund der Browser-Erweiterung (#153, DEC-026): lädt den öffentlichen Index (einmal täglich, ETag) und
 * registriert das Inhaltsscript für die freigegebenen Websites. Es werden keine Seiteninhalte oder besuchten
 * Adressen übertragen oder gespeichert.
 */
import { loadIndex } from '../../packages/highlighter/src/loader';
import type { IndexStore } from '../../packages/highlighter/src/loader';
import { sendReport } from '../../packages/highlighter/src/report';
import type { ReportPayload } from '../../packages/highlighter/src/report';
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

const MENU_ID = 'gls-report';

/** Kontextmenü «An EduKSX-Glossary senden» (#480): nur für markierten Text. */
function createMenu(): void {
    chrome.contextMenus.removeAll(() => {
        chrome.contextMenus.create({
            id: MENU_ID,
            title: 'An EduKSX-Glossary senden',
            contexts: ['selection'],
        });
    });
}

/** Wer bin ich im Glossary (Sitzung des Browsers)? Ohne Antwort: anonym. */
async function whoAmI(origin: string): Promise<{
    angemeldet: boolean;
    name: string | null;
    anonym: boolean;
    anmelden: string;
}> {
    try {
        const res = await fetch(`${origin}/erweiterung/ich`, {
            credentials: 'include',
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            return (await res.json()) as {
                angemeldet: boolean;
                name: string | null;
                anonym: boolean;
                anmelden: string;
            };
        }
    } catch {
        // offline o. Ä.: anonym weiter
    }

    return {
        angemeldet: false,
        name: null,
        anonym: true,
        anmelden: `${origin}/anmelden`,
    };
}

chrome.contextMenus.onClicked.addListener((info, tab) => {
    if (info.menuItemId !== MENU_ID || tab?.id === undefined) {
        return;
    }
    const tabId = tab.id;
    void (async () => {
        // nur der markierte Text, gekürzt; die Seite selbst wird nicht gelesen (activeTab durch den Klick)
        await chrome.scripting.executeScript({
            target: { tabId },
            files: ['report.js'],
        });
        await chrome.tabs.sendMessage(tabId, {
            type: 'report-open',
            word: (info.selectionText ?? '').slice(0, 200),
            kind: 'fehlt',
        });
    })();
});

chrome.runtime.onInstalled.addListener(() => {
    void syncSites();
    createMenu();
});
chrome.runtime.onStartup.addListener(createMenu);
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
                        index: await loadIndex(
                            settings.origin,
                            store,
                            fetch,
                            'extension',
                        ),
                    });
                } catch (error) {
                    respond({ ok: false, error: String(error) });
                }
            })();

            return true;
        }
        // Begriff melden (#480): Anmeldung erkennen und senden mit der Sitzung des Browsers
        if (message.type === 'report-me') {
            void (async () => {
                respond(await whoAmI((await getSettings()).origin));
            })();

            return true;
        }
        if (message.type === 'report-send') {
            void (async () => {
                const { origin } = await getSettings();
                try {
                    respond(
                        await sendReport(
                            origin,
                            (message as { payload: ReportPayload }).payload,
                            'include',
                        ),
                    );
                } catch {
                    respond({
                        ok: false,
                        text: 'Senden nicht möglich – bitte später nochmals.',
                    });
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
