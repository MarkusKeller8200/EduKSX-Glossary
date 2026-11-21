import { Matcher } from './matcher';
import { Tooltip } from './tooltip';
import type { HighlightOptions, IndexEntry, ScanOptions } from './types';

/** Markierte Stelle; data-gls-codes enthält die Codes (mehrdeutig: mehrere, mit Komma). */
export const MARK_CLASS = 'gls-term';

/** Diese Elemente werden nie durchsucht (Code, Formulare, eigene Markierungen, Glossary-Links). */
const SKIP = new Set([
    'SCRIPT',
    'STYLE',
    'NOSCRIPT',
    'TEMPLATE',
    'CODE',
    'PRE',
    'KBD',
    'SAMP',
    'TEXTAREA',
    'INPUT',
    'SELECT',
    'OPTION',
    'BUTTON',
    'SVG',
    'MATH',
    'IFRAME',
    'CANVAS',
    'GLOSSARY-TERM',
    'ABBR',
]);

const MARK_STYLE = `.${MARK_CLASS}{text-decoration:underline dotted;text-decoration-thickness:1px;text-underline-offset:.18em;cursor:help;border-radius:2px}.${MARK_CLASS}:focus-visible{outline:2px solid #0272ad;outline-offset:1px}`;

/**
 * Hebt Begriffe des Glossary in einem Bereich der Seite hervor (DEC-026). Die Suche läuft lokal; es verlässt kein
 * Seiteninhalt den Browser. Inhalte nur über textContent, Links nur zum festen Ursprung.
 */
export class Highlighter {
    private readonly matcher: Matcher;

    private readonly byCode = new Map<string, IndexEntry>();

    private readonly tooltip: Tooltip;

    /** Bereits markierte Begriffe (firstOnly) mit ihrer Markierung, um abgelöste wieder freizugeben (#474). */
    private readonly seen = new Map<string, Element>();

    private observer: MutationObserver | null = null;

    private marks = 0;

    constructor(
        entries: IndexEntry[],
        private readonly options: HighlightOptions,
        private readonly doc: Document = document,
    ) {
        this.matcher = new Matcher(entries, options);
        for (const e of entries) {
            if (e.code) {
                this.byCode.set(e.code, e);
            }
        }
        this.tooltip = new Tooltip(doc, options.origin);
        this.injectStyle();
    }

    /** Einträge zu einem Code (für <glossary-term>). */
    entry(code: string): IndexEntry | undefined {
        return this.byCode.get(code.toUpperCase());
    }

    /**
     * Bereich durchsuchen und Treffer markieren; gibt die Anzahl neuer Markierungen zurück. Mit
     * `{ firstOnly: true }` gilt «nur erste Fundstelle» je Durchgang (Ansicht) statt für die ganze Seite (#474).
     */
    scan(root: Node, scanOptions: ScanOptions = {}): number {
        const max = this.options.maxMatches ?? 300;
        const seen =
            scanOptions.firstOnly === true
                ? new Map<string, Element>()
                : this.options.firstOnly
                  ? this.seen
                  : null;
        const before = this.marks;
        const texts: Text[] = [];
        const walker = this.doc.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: (node) =>
                this.skip(node)
                    ? NodeFilter.FILTER_REJECT
                    : NodeFilter.FILTER_ACCEPT,
        });
        for (let n = walker.nextNode(); n !== null; n = walker.nextNode()) {
            texts.push(n as Text);
        }
        for (const text of texts) {
            if (this.marks >= max) {
                break;
            }
            this.mark(text, max, seen);
        }

        return this.marks - before;
    }

    /** Gemerkte Begriffe für «nur erste Fundstelle» vergessen, z. B. bei einem Ansichtswechsel (#474). */
    reset(): void {
        this.seen.clear();
    }

    /** Nachgeladene Inhalte ebenfalls durchsuchen (mit kurzer Verzögerung). */
    observe(root: Node): void {
        let pending: Node[] = [];
        let timer: number | undefined;
        this.observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                m.addedNodes.forEach((n) => {
                    if (
                        !(
                            n instanceof Element &&
                            n.classList.contains(MARK_CLASS)
                        )
                    ) {
                        pending.push(n);
                    }
                });
            }
            window.clearTimeout(timer);
            timer = window.setTimeout(() => {
                const nodes = pending;
                pending = [];
                this.observer?.disconnect();
                for (const n of nodes) {
                    if (n.isConnected) {
                        this.scan(n);
                    }
                }
                this.observer?.observe(root, {
                    childList: true,
                    subtree: true,
                });
            }, 400);
        });
        this.observer.observe(root, { childList: true, subtree: true });
    }

    /** Bindet die Erklärbox an ein vorhandenes Element (z. B. <glossary-term>). */
    attach(element: HTMLElement, entries: IndexEntry[]): void {
        this.tooltip.bind(element, entries);
    }

    /** Markierungen entfernen und Beobachtung beenden. */
    destroy(root: ParentNode = this.doc.body): void {
        this.observer?.disconnect();
        root.querySelectorAll(`span.${MARK_CLASS}`).forEach((span) => {
            span.replaceWith(this.doc.createTextNode(span.textContent ?? ''));
        });
        if (root instanceof Element) {
            root.normalize();
        }
        this.tooltip.destroy();
        this.marks = 0;
        this.seen.clear();
    }

    private skip(node: Node): boolean {
        if (!node.nodeValue || node.nodeValue.trim().length < 2) {
            return true;
        }
        for (let el = node.parentElement; el !== null; el = el.parentElement) {
            if (
                SKIP.has(el.tagName.toUpperCase()) ||
                el.isContentEditable ||
                el.classList.contains(MARK_CLASS) ||
                el.hasAttribute('data-glossary-ignore')
            ) {
                return true;
            }
            if (
                el.tagName === 'A' &&
                (el as HTMLAnchorElement).href.startsWith(this.options.origin)
            ) {
                return true;
            }
        }

        return false;
    }

    private mark(
        text: Text,
        max: number,
        seen: Map<string, Element> | null,
    ): void {
        const value = text.nodeValue ?? '';
        let matches = this.matcher.find(value);
        const keys = new Map<(typeof matches)[number], string>();
        if (seen !== null) {
            const inThisText = new Set<string>();
            matches = matches.filter((m) => {
                const key = m.entries.map((e) => e.code).join(',');
                const previous = seen.get(key);
                const free =
                    previous === undefined ||
                    (this.options.releaseDetached === true &&
                        seen === this.seen &&
                        !previous.isConnected);
                if (!free || inThisText.has(key)) {
                    return false;
                }
                inThisText.add(key);
                keys.set(m, key);

                return true;
            });
        }
        if (matches.length === 0) {
            return;
        }
        const fragment = this.doc.createDocumentFragment();
        let at = 0;
        for (const m of matches) {
            if (this.marks >= max) {
                break;
            }
            fragment.append(this.doc.createTextNode(value.slice(at, m.start)));
            const span = this.doc.createElement('span');
            span.className = MARK_CLASS;
            span.textContent = m.text;
            span.tabIndex = 0;
            span.dataset.glsCodes = m.entries
                .map((e) => e.code ?? '')
                .filter(Boolean)
                .join(',');
            this.tooltip.bind(span, m.entries);
            const key = keys.get(m);
            if (seen !== null && key !== undefined) {
                seen.set(key, span);
            }
            fragment.append(span);
            at = m.end;
            this.marks++;
        }
        fragment.append(this.doc.createTextNode(value.slice(at)));
        text.replaceWith(fragment);
    }

    private injectStyle(): void {
        if (this.doc.getElementById('gls-style') !== null) {
            return;
        }
        const style = this.doc.createElement('style');
        style.id = 'gls-style';
        style.textContent = MARK_STYLE;
        this.doc.head.append(style);
    }
}
