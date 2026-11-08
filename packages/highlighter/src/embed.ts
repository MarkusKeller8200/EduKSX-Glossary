/**
 * Einbindung für Entwickler (#155, DEC-026): eine Script-Zeile auf der eigenen Webseite.
 *
 *   <script src="https://glossary.eduksx.ch/embed/v1/glossary.js" integrity="sha384-…" crossorigin="anonymous"
 *           defer data-scope="#inhalt" data-first-only="true"></script>
 *   <glossary-term code="7KQ2MX">SL</glossary-term>
 *
 * Optionen (data-…): scope (CSS-Selektor, Vorgabe «main, article, [data-glossary]»; «none» = nur <glossary-term>),
 * names, first-only, categories (Labels mit Komma), max, observe. Keine Cookies, kein Tracking; Daten aus dem
 * öffentlichen Index, Suche nur im Browser.
 */
import { Highlighter, loadIndex, MARK_CLASS } from './index';
import type { HighlightOptions, IndexEntry } from './types';

const EMBED_VERSION = '1.0.0';

type Api = {
    version: string;
    ready: Promise<Highlighter>;
    scan: (root: Element) => Promise<number>;
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

const options: HighlightOptions = {
    origin,
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
    const index = await loadIndex(origin);
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
    scan: async (root: Element) => (await ready).scan(root),
};

const run = (): void => {
    start().catch((error: unknown) => console.warn('EduKSX-Glossary:', error));
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
} else {
    run();
}
