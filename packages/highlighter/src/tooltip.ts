import type { IndexEntry } from './types';

/** Gültiger Code eines Permalinks (wie App\Glossary\Permalink::PATTERN). */
export const CODE_PATTERN = /^[0-9A-HJKMNP-TV-Z]{6}$/;

const STYLE = `
:host{all:initial}
.box{position:fixed;z-index:2147483647;max-width:22rem;padding:.6rem .75rem;border:1px solid #d5dbe3;border-radius:.5rem;
background:#fff;color:#1f2933;box-shadow:0 6px 24px rgba(15,23,42,.18);font:14px/1.4 Inter,system-ui,sans-serif}
.box[hidden]{display:none}
.item+.item{margin-top:.5rem;padding-top:.5rem;border-top:1px solid #eef1f4}
.title{font-weight:600}.abbr{color:#0272ad}.text{margin:.2rem 0 0}
a{color:#0272ad;text-decoration:underline;text-underline-offset:2px}
.foot{margin-top:.45rem;font-size:12px;color:#5f6b7a}
@media (prefers-color-scheme:dark){.box{background:#1c2128;color:#e6edf3;border-color:#30363d}.abbr,a{color:#58a6ff}.foot{color:#9aa4af}.item+.item{border-color:#30363d}}
`;

/** Link nur zum Ursprung des Glossary, aus geprüftem Code gebaut (DEC-026, E1). */
export function permalink(origin: string, code: string | null): string | null {
    return code !== null && CODE_PATTERN.test(code)
        ? `${origin.replace(/\/+$/, '')}/g/${code}`
        : null;
}

/**
 * Erklärbox (WCAG 2.1): öffnet bei Maus und Tastaturfokus, bleibt offen, solange die Maus darüber ist, schliesst mit
 * Esc. Eine Box je Seite in einem Shadow DOM (Stil der Seite wirkt nicht hinein). Inhalt nur über textContent.
 */
export class Tooltip {
    private host: HTMLElement | null = null;

    private box: HTMLElement | null = null;

    private hideTimer: number | undefined;

    private readonly onKey = (e: KeyboardEvent): void => {
        if (e.key === 'Escape') {
            this.hide();
        }
    };

    constructor(
        private readonly doc: Document,
        private readonly origin: string,
    ) {}

    bind(target: HTMLElement, entries: IndexEntry[]): void {
        const id = this.ensure().id;
        target.setAttribute('aria-describedby', id);
        const show = (): void => this.show(target, entries);
        target.addEventListener('mouseenter', show);
        target.addEventListener('focus', show);
        target.addEventListener('mouseleave', () => this.scheduleHide());
        target.addEventListener('blur', () => this.scheduleHide());
    }

    destroy(): void {
        this.doc.removeEventListener('keydown', this.onKey);
        this.host?.remove();
        this.host = null;
        this.box = null;
    }

    private ensure(): HTMLElement {
        if (this.box !== null) {
            return this.box;
        }
        this.host = this.doc.createElement('div');
        this.host.setAttribute('data-glossary-ignore', '');
        const shadow = this.host.attachShadow({ mode: 'open' });
        const style = this.doc.createElement('style');
        style.textContent = STYLE;
        this.box = this.doc.createElement('div');
        this.box.className = 'box';
        this.box.id = 'gls-tooltip';
        this.box.setAttribute('role', 'tooltip');
        this.box.hidden = true;
        this.box.addEventListener('mouseenter', () =>
            window.clearTimeout(this.hideTimer),
        );
        this.box.addEventListener('mouseleave', () => this.scheduleHide());
        shadow.append(style, this.box);
        this.doc.body.append(this.host);
        this.doc.addEventListener('keydown', this.onKey);

        return this.box;
    }

    private show(target: HTMLElement, entries: IndexEntry[]): void {
        const box = this.ensure();
        window.clearTimeout(this.hideTimer);
        box.replaceChildren(...entries.map((e) => this.item(e)), this.footer());
        box.hidden = false;
        const r = target.getBoundingClientRect();
        const width = Math.min(352, window.innerWidth - 16);
        box.style.left = `${Math.max(8, Math.min(r.left, window.innerWidth - width - 8))}px`;
        const below = r.bottom + 6;
        box.style.top = `${below + 140 > window.innerHeight && r.top > 160 ? Math.max(8, r.top - 6 - box.offsetHeight) : below}px`;
    }

    private scheduleHide(): void {
        window.clearTimeout(this.hideTimer);
        this.hideTimer = window.setTimeout(() => this.hide(), 200);
    }

    private hide(): void {
        if (this.box !== null) {
            this.box.hidden = true;
        }
    }

    private item(entry: IndexEntry): HTMLElement {
        const item = this.doc.createElement('div');
        item.className = 'item';
        const title = this.doc.createElement('div');
        title.className = 'title';
        if (entry.abbreviation) {
            const abbr = this.doc.createElement('span');
            abbr.className = 'abbr';
            abbr.textContent = entry.abbreviation;
            title.append(abbr, entry.name ? ' – ' : '');
        }
        title.append(entry.name ?? '');
        item.append(title);
        if (entry.short_description) {
            const text = this.doc.createElement('p');
            text.className = 'text';
            text.textContent = entry.short_description;
            item.append(text);
        }
        const href = permalink(this.origin, entry.code);
        if (href !== null) {
            const link = this.doc.createElement('a');
            link.href = href;
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = 'Im Glossary öffnen';
            const p = this.doc.createElement('div');
            p.append(link);
            item.append(p);
        }

        return item;
    }

    private footer(): HTMLElement {
        const foot = this.doc.createElement('div');
        foot.className = 'foot';
        foot.textContent = 'Quelle: EduKSX-Glossary, CC BY 4.0';

        return foot;
    }
}
