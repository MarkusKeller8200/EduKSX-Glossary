/**
 * Einbindung für Entwickler (#155, DEC-026): eine Script-Zeile auf der eigenen Webseite.
 *
 *   <script src="https://glossary.eduksx.ch/embed/v1/glossary.js" integrity="sha384-…" crossorigin="anonymous"
 *           defer data-scope="#inhalt" data-first-only="true"></script>
 *   <glossary-term code="7KQ2MX">SL</glossary-term>
 *
 * Optionen (data-…): scope (CSS-Selektor, Vorgabe «main, article, [data-glossary]»; «none» = nur <glossary-term>),
 * names, first-only, categories (Labels mit Komma), max, observe. Single-Page-Apps: nach einem Ansichtswechsel
 * `EduksxGlossary.scan(element, { firstOnly: true })` bzw. zuerst `EduksxGlossary.reset()` aufrufen (#474).
 * Mit data-report="true": «Erklärung falsch?» in der Erklärbox und `EduksxGlossary.report(wort)` (#480). Keine Cookies, kein Tracking; Daten aus dem
 * öffentlichen Index, Suche nur im Browser.
 */
import {
    Highlighter,
    loadIndex,
    localStore,
    MARK_CLASS,
    openReportDialog,
    sendReport,
} from './index';
import type { HighlightOptions, IndexEntry, ScanOptions } from './types';

const EMBED_VERSION = '1.1.1';

type Api = {
    version: string;
    ready: Promise<Highlighter>;
    /** Bereich durchsuchen; `{ firstOnly: true }` = erste Fundstelle je Begriff in diesem Bereich (#474). */
    scan: (root: Element, options?: ScanOptions) => Promise<number>;
    /** Gemerkte Begriffe für «nur erste Fundstelle» vergessen (Ansichtswechsel, #474). */
    reset: () => Promise<void>;
    /** Dialog «Fehlt im Glossary» für ein Wort öffnen (#480, mit data-report="true"). */
    report: (word: string) => void;
};

declare global {
    interface Window {
        EduksxGlossary?: Api;
    }
}

const script = document.currentScript as HTMLScriptElement | null;
const data = script?.dataset ?? {};
const origin =
    data.origin ??
    (script?.src ? new URL(script.src).origin : window.location.origin);
const flag = (v: string | undefined): boolean =>
    v === '' || v === 'true' || v === '1';

// «Erklärung falsch?» und «Fehlt im Glossary» (#480): nur mit data-report="true"; die Webseite muss in der
// Administration des Glossary eingetragen sein, sonst blockiert der Browser das Senden (CORS)
const report = flag(data.report);
const reportTerm = (entry: IndexEntry): void => {
    openReportDialog(document, {
        word: entry.abbreviation ?? entry.name ?? '',
        kind: 'falsch',
        code: entry.code,
        send: (payload) => sendReport(origin, payload),
    });
};

const options: HighlightOptions = {
    origin,
    via: 'einbindung',
    onReport: report ? reportTerm : undefined,
    names: flag(data.names),
    firstOnly: flag(data.firstOnly),
    maxMatches: data.max ? Number(data.max) : 300,
    categories: data.categories
        ? data.categories
              .split(',')
              .map((c) => c.trim())
              .filter(Boolean)
        : [],
};

let resolveReady: (h: Highlighter) => void = () => undefined;
const ready = new Promise<Highlighter>((resolve) => {
    resolveReady = resolve;
});

/** <glossary-term code="…">: ausdrücklich markierter Begriff, ohne automatische Suche. */
class GlossaryTerm extends HTMLElement {
    connectedCallback(): void {
        void ready.then((h) => {
            const code = (this.getAttribute('code') ?? '').trim();
            const entry: IndexEntry | undefined = h.entry(code);
            if (entry === undefined) {
                return;
            }
            if ((this.textContent ?? '').trim() === '') {
                this.textContent = entry.abbreviation ?? entry.name ?? code;
            }
            this.classList.add(MARK_CLASS);
            this.tabIndex = 0;
            h.attach(this, [entry]);
        });
    }
}

async function start(): Promise<void> {
    const index = await loadIndex(origin, localStore, fetch, 'embed');
    const highlighter = new Highlighter(index.terms, options);
    resolveReady(highlighter);
    const scope = data.scope ?? 'main, article, [data-glossary]';
    if (scope !== 'none') {
        const roots = Array.from(document.querySelectorAll(scope));
        for (const root of roots) {
            highlighter.scan(root);
            if (flag(data.observe)) {
                highlighter.observe(root);
            }
        }
    }
}

if (!customElements.get('glossary-term')) {
    customElements.define('glossary-term', GlossaryTerm);
}

window.EduksxGlossary = {
    version: EMBED_VERSION,
    ready,
    scan: async (root: Element, scanOptions?: ScanOptions) =>
        (await ready).scan(root, scanOptions),
    reset: async () => (await ready).reset(),
    report: (word: string) => {
        openReportDialog(document, {
            word,
            kind: 'fehlt',
            send: (payload) => sendReport(origin, payload),
        });
    },
};

const run = (): void => {
    start().catch((error: unknown) => console.warn('EduKSX-Glossary:', error));
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
} else {
    run();
}
