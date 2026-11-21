/**
 * «An EduKSX-Glossary senden» (#480, DEC-042): wird nur nach dem Klick im Kontextmenü bzw. auf «Erklärung falsch?»
 * eingefügt, zeigt Vorschau und Bestätigung und schickt die Meldung über den Hintergrund (mit der Sitzung des
 * Browsers, damit angemeldete Personen erkannt werden). Übertragen werden nur Wort, Kommentar und Adresse ohne Abfrage.
 */
import { openReportDialog } from '../../packages/highlighter/src/report';
import type {
    ReportKind,
    ReportPayload,
    ReportResult,
} from '../../packages/highlighter/src/report';

declare global {
    interface Window {
        __eduksxReport?: (
            word: string,
            kind: ReportKind,
            code?: string | null,
        ) => Promise<void>;
    }
}

type Me = {
    angemeldet: boolean;
    name: string | null;
    anonym: boolean;
    anmelden: string;
};

async function open(
    word: string,
    kind: ReportKind,
    code?: string | null,
): Promise<void> {
    const me = (await chrome.runtime.sendMessage({ type: 'report-me' })) as Me;
    openReportDialog(document, {
        word,
        kind,
        code,
        account: me.angemeldet ? (me.name ?? 'angemeldet') : null,
        anonymous: me.anonym,
        loginUrl: me.anmelden,
        send: async (payload: ReportPayload) =>
            (await chrome.runtime.sendMessage({
                type: 'report-send',
                payload,
            })) as ReportResult,
    });
}

if (window.__eduksxReport === undefined) {
    window.__eduksxReport = open;
    chrome.runtime.onMessage.addListener(
        (
            message: {
                type?: string;
                word?: string;
                kind?: ReportKind;
                code?: string;
            },
            sender,
        ) => {
            if (
                sender.id !== chrome.runtime.id ||
                message.type !== 'report-open'
            ) {
                return false;
            }
            void open(
                message.word ?? '',
                message.kind ?? 'fehlt',
                message.code ?? null,
            );

            return false;
        },
    );
}
