/**
 * Inhaltsscript (#153): hebt Begriffe auf der aktuellen Seite hervor. Erneutes Ausführen (Klick im Menü) schaltet
 * die Hervorhebung wieder aus. Der Seitentext wird nur hier im Browser durchsucht.
 */
import { Highlighter } from '../../packages/highlighter/src/highlighter';
import type { IndexResponse } from '../../packages/highlighter/src/types';
import type { Settings } from './settings';

type Reply =
    | { ok: true; settings: Settings; index: IndexResponse }
    | { ok: false; error: string };

declare global {
    interface Window {
        __eduksxGlossary?: Highlighter;
    }
}

async function run(): Promise<void> {
    if (window.__eduksxGlossary !== undefined) {
        window.__eduksxGlossary.destroy();
        window.__eduksxGlossary = undefined;

        return;
    }
    const reply = (await chrome.runtime.sendMessage({
        type: 'index',
    })) as Reply;
    if (!reply.ok) {
        console.warn('EduKSX-Glossary:', reply.error);

        return;
    }
    // im Glossary selbst nicht markieren
    if (window.location.origin === reply.settings.origin) {
        return;
    }
    const highlighter = new Highlighter(reply.index.terms, {
        origin: reply.settings.origin,
        names: reply.settings.names,
        firstOnly: reply.settings.firstOnly,
    });
    window.__eduksxGlossary = highlighter;
    highlighter.scan(document.body);
    highlighter.observe(document.body);
}

void run();
