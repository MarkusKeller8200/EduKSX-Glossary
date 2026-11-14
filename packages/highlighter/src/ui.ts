/**
 * Web-Komponenten für Gastgeber wie Intranet und Portal (#338, KONZEPT-PLUGINS P1–P7 in EduKSX-Architektur):
 *
 *   <script src="https://glossary.eduksx.ch/ui/v1/glossary.js" defer></script>
 *   <glossary-search placeholder="Begriff oder Abkürzung" limit="8"></glossary-search>
 *
 * Nur öffentliche Daten über die öffentliche API (ohne Anmeldung, P7), keine Cookies, kein Tracking. Der Steckbrief
 * (/.well-known/eduksx-module.json, Feld «ui») nennt dieses Script und die Kachel «term-search».
 */

const UI_VERSION = '1.0.0';

type Term = {
    abbreviation: string | null;
    name: string;
    short_description: string | null;
    web_url: string;
};

const script = document.currentScript as HTMLScriptElement | null;
const defaultOrigin = script?.src
    ? new URL(script.src).origin
    : window.location.origin;

const STYLE = `
:host { display: block; font: inherit; color: inherit; }
.feld { width: 100%; box-sizing: border-box; padding: .5rem .75rem; border: 1px solid #c8ccd2; border-radius: .375rem; font: inherit; }
.feld:focus { outline: 2px solid #f39200; outline-offset: 1px; }
ul { list-style: none; margin: .5rem 0 0; padding: 0; }
li { padding: .5rem 0; border-bottom: 1px solid #e5e7eb; }
a { color: inherit; font-weight: 600; text-decoration: none; }
a:hover, a:focus { text-decoration: underline; }
.kurz { display: block; font-size: .875rem; opacity: .8; margin-top: .125rem; }
.hinweis { font-size: .875rem; opacity: .75; margin: .5rem 0 0; }
.quelle { font-size: .75rem; opacity: .6; margin-top: .5rem; }
`;

/** <glossary-search>: Suche im Glossary mit Trefferliste; Treffer öffnen die Begriffsseite. */
class GlossarySearch extends HTMLElement {
    private input!: HTMLInputElement;

    private list!: HTMLUListElement;

    private status!: HTMLParagraphElement;

    private timer: number | undefined;

    private controller: AbortController | undefined;

    connectedCallback(): void {
        if (this.shadowRoot) {
            return;
        }
        const root = this.attachShadow({ mode: 'open' });
        const style = document.createElement('style');
        style.textContent = STYLE;
        const label = document.createElement('label');
        label.textContent = this.getAttribute('label') ?? 'Im Glossary suchen';
        label.style.cssText =
            'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)';
        label.htmlFor = 'q';
        this.input = document.createElement('input');
        this.input.id = 'q';
        this.input.type = 'search';
        this.input.className = 'feld';
        this.input.autocomplete = 'off';
        this.input.placeholder =
            this.getAttribute('placeholder') ?? 'Begriff oder Abkürzung';
        this.list = document.createElement('ul');
        this.status = document.createElement('p');
        this.status.className = 'hinweis';
        this.status.setAttribute('role', 'status');
        const source = document.createElement('div');
        source.className = 'quelle';
        source.textContent = 'Quelle: EduKSX-Glossary (CC BY 4.0)';
        source.title = `Web-Komponenten v${UI_VERSION}`;
        root.append(style, label, this.input, this.status, this.list, source);
        this.input.addEventListener('input', () => {
            window.clearTimeout(this.timer);
            this.timer = window.setTimeout(() => void this.search(), 250);
        });
    }

    private get origin(): string {
        return this.getAttribute('origin') ?? defaultOrigin;
    }

    private async search(): Promise<void> {
        const q = this.input.value.trim();
        this.controller?.abort();
        this.list.replaceChildren();
        if (q.length < 2) {
            this.status.textContent = '';

            return;
        }
        this.controller = new AbortController();
        const limit = Math.min(
            50,
            Math.max(1, Number(this.getAttribute('limit') ?? 8) || 8),
        );
        const url = `${this.origin}/api/v1/terms?q=${encodeURIComponent(q)}&limit=${limit}&locale=${encodeURIComponent(document.documentElement.lang || 'de')}`;
        try {
            const res = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: this.controller.signal,
            });
            if (!res.ok) {
                throw new Error(String(res.status));
            }
            const body = (await res.json()) as { data: Term[] };
            this.render(body.data);
        } catch (e) {
            if ((e as Error).name !== 'AbortError') {
                this.status.textContent =
                    'Die Suche ist im Moment nicht erreichbar.';
            }
        }
    }

    private render(terms: Term[]): void {
        this.status.textContent =
            terms.length === 0
                ? 'Kein Begriff gefunden.'
                : `${terms.length} Begriff${terms.length === 1 ? '' : 'e'} gefunden.`;
        for (const t of terms) {
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = t.web_url;
            a.target = '_blank';
            a.rel = 'noopener';
            a.textContent = t.abbreviation
                ? `${t.abbreviation} – ${t.name}`
                : t.name;
            li.append(a);
            if (t.short_description) {
                const s = document.createElement('span');
                s.className = 'kurz';
                s.textContent = t.short_description;
                li.append(s);
            }
            this.list.append(li);
        }
    }
}

if (!customElements.get('glossary-search')) {
    customElements.define('glossary-search', GlossarySearch);
}
