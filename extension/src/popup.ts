/**
 * Menü der Erweiterung (#153): auf der aktuellen Seite hervorheben (nur mit «activeTab», ohne dauernde Berechtigung)
 * oder für die Website immer aktivieren (Berechtigung nur für diese Website, jederzeit widerrufbar).
 */
import { getSettings, originOf, saveSettings } from './settings';

const $ = <T extends HTMLElement>(id: string): T =>
    document.getElementById(id) as T;

async function init(): Promise<void> {
    const [tab] = await chrome.tabs.query({
        active: true,
        currentWindow: true,
    });
    const site = originOf(tab?.url);
    const settings = await getSettings();

    $('site').textContent = site ?? 'Diese Seite kann nicht durchsucht werden.';
    $<HTMLAnchorElement>('open').href = settings.origin;
    $<HTMLInputElement>('names').checked = settings.names;
    $<HTMLInputElement>('firstOnly').checked = settings.firstOnly;
    const always = $<HTMLInputElement>('always');
    always.checked = site !== null && settings.sites.includes(site);
    $<HTMLButtonElement>('run').disabled =
        site === null || tab?.id === undefined;
    always.disabled = site === null;

    $('run').addEventListener('click', async () => {
        if (tab?.id === undefined) {
            return;
        }
        await chrome.scripting.executeScript({
            target: { tabId: tab.id },
            files: ['content.js'],
        });
        window.close();
    });

    always.addEventListener('change', async () => {
        if (site === null) {
            return;
        }
        const pattern = { origins: [`${site}/*`] };
        if (always.checked) {
            // Berechtigung nur für diese Website (Benutzergeste nötig)
            const granted = await chrome.permissions.request(pattern);
            if (!granted) {
                always.checked = false;

                return;
            }
            // die Liste führt der Hintergrund nach (permissions.onAdded), auch wenn das Menü schliesst
        } else {
            await chrome.permissions.remove(pattern);
        }
        await chrome.runtime.sendMessage({ type: 'sync' });
        $('status').textContent = always.checked
            ? 'Auf dieser Website immer aktiv.'
            : 'Nicht mehr automatisch aktiv.';
    });

    // Anmeldung im Glossary erkennen (#480): nur Anzeige, ohne Personendaten ausser dem Community-Namen
    void (
        chrome.runtime.sendMessage({ type: 'report-me' }) as Promise<{
            angemeldet: boolean;
            name: string | null;
        }>
    ).then((me) => {
        $('account').textContent = me.angemeldet
            ? `Im Glossary angemeldet als ${me.name ?? 'Konto'}.`
            : 'Im Glossary nicht angemeldet – Meldungen gehen anonym.';
    });

    for (const key of ['names', 'firstOnly'] as const) {
        $<HTMLInputElement>(key).addEventListener('change', async (e) => {
            await saveSettings({
                [key]: (e.target as HTMLInputElement).checked,
            });
            $('status').textContent =
                'Gespeichert – gilt beim nächsten Hervorheben.';
        });
    }
}

void init();
