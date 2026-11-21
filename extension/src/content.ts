/**
 * Inhaltsscript (#153): hebt Begriffe auf der aktuellen Seite hervor. Erneutes Ausführen (Klick im Menü) schaltet
 * die Hervorhebung wieder aus. Der Seitentext wird nur hier im Browser durchsucht.
 */
import { Highlighter } from '../../packages/highlighter/src/highlighter';
import { openReportDialog } from '../../packages/highlighter/src/report';
import type { ReportResult } from '../../packages/highlighter/src/report';
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
        // Ansichtswechsel ohne Neuladen (#474): abgelöste Markierungen geben den Begriff wieder frei
        releaseDetached: true,
        via: 'erweiterung',
        // «Erklärung falsch?» (#480): Melde-Dialog wie im Kontextmenü
        onReport: (entry) =>
            void report(entry.abbreviation ?? entry.name ?? '', entry.code),
    });
    window.__eduksxGlossary = highlighter;
    highlighter.scan(document.body);
    highlighter.observe(document.body);
}

/** Melde-Dialog im selben Script öffnen (Anmeldung erkennt der Hintergrund). */
async function report(word: string, code: string | null): Promise<void> {
    const me = (await chrome.runtime.sendMessage({ type: 'report-me' })) as {
        angemeldet: boolean;
        name: string | null;
        anonym: boolean;
        anmelden: string;
    };
    openReportDialog(document, {
        word,
        kind: 'falsch',
        code,
        account: me.angemeldet ? (me.name ?? 'angemeldet') : null,
        anonymous: me.anonym,
        loginUrl: me.anmelden,
        send: async (payload) =>
            (await chrome.runtime.sendMessage({
                type: 'report-send',
                payload,
            })) as ReportResult,
    });
}

void run();
