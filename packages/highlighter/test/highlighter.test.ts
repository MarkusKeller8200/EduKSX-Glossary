import { describe, expect, it } from 'vitest';
import { Highlighter, loadIndex, Matcher, MARK_CLASS, permalink } from '../src';
import type { IndexEntry } from '../src';

const ORIGIN = 'https://glossary.eduksx.ch';

const entry = (
    code: string,
    abbreviation: string | null,
    name: string | null,
    extra: Partial<IndexEntry> = {},
): IndexEntry => ({
    code,
    abbreviation,
    name,
    aliases: [],
    short_description: `Kurztext ${name ?? abbreviation}`,
    is_ambiguous: false,
    categories: [],
    permalink: `${ORIGIN}/g/${code}`,
    ...extra,
});

const ENTRIES = [
    entry('7KQ2MX', 'SL', 'Schulleitung', { categories: ['Schule'] }),
    entry('8ABCDE', 'SHP', 'Schulische Heilpädagogik', {
        aliases: ['Heilpädagogik'],
    }),
    entry('9ZZZZZ', 'LP', 'Lehrperson'),
    entry('AAAAAA', 'LP', 'Lehrplan', { is_ambiguous: true }),
];

describe('Matcher', () => {
    it('findet Abkürzungen nur mit exakter Schreibweise und an Wortgrenzen', () => {
        const m = new Matcher(ENTRIES);
        expect(m.find('Die SL und die SHP.').map((x) => x.text)).toEqual([
            'SL',
            'SHP',
        ]);
        expect(m.find('Slogan, sl, SLP, ÄSL')).toEqual([]);
    });

    it('mehrdeutige Abkürzung liefert alle Bedeutungen', () => {
        const [hit] = new Matcher(ENTRIES).find('Die LP plant.');
        expect(hit.entries.map((e) => e.name)).toEqual([
            'Lehrperson',
            'Lehrplan',
        ]);
    });

    it('Bezeichnungen optional, ohne Gross-/Kleinschreibung, längster Treffer gewinnt', () => {
        const m = new Matcher(ENTRIES, { names: true });
        expect(
            m.find('schulische heilpädagogik ist wichtig').map((x) => x.text),
        ).toEqual(['schulische heilpädagogik']);
        expect(new Matcher(ENTRIES).find('Schulleitung')).toEqual([]);
    });

    it('filtert nach Kategorien', () => {
        expect(
            new Matcher(ENTRIES, { categories: ['Schule'] })
                .find('SL und LP')
                .map((x) => x.text),
        ).toEqual(['SL']);
    });
});

describe('permalink', () => {
    it('baut Links nur aus gültigem Code und festem Ursprung', () => {
        expect(permalink(ORIGIN, '7KQ2MX')).toBe(`${ORIGIN}/g/7KQ2MX`);
        expect(permalink(ORIGIN, '../x')).toBeNull();
        expect(permalink(ORIGIN, 'javascript:alert(1)')).toBeNull();
        expect(permalink(ORIGIN, null)).toBeNull();
    });
});

describe('Highlighter', () => {
    it('markiert Text, überspringt Code und Formularfelder, ohne HTML einzuschleusen', () => {
        document.body.innerHTML =
            '<p id="t">Die SL und <code>SL</code> <textarea>SL</textarea> <span data-glossary-ignore>SL</span></p>';
        const evil = [entry('7KQ2MX', 'SL', '<img src=x onerror=alert(1)>')];
        const h = new Highlighter(evil, { origin: ORIGIN });
        expect(h.scan(document.body)).toBe(1);
        const mark = document.querySelector(`.${MARK_CLASS}`) as HTMLElement;
        expect(mark.textContent).toBe('SL');
        expect(mark.tabIndex).toBe(0);
        expect(mark.dataset.glsCodes).toBe('7KQ2MX');

        mark.dispatchEvent(new Event('focus'));
        const box = document
            .querySelector('[data-glossary-ignore]:not(span)')
            ?.shadowRoot?.querySelector('.box') as HTMLElement;
        expect(box.hidden).toBe(false);
        expect(box.querySelector('img')).toBeNull();
        expect(box.textContent).toContain('<img src=x onerror=alert(1)>');
        expect(box.querySelector('a')?.getAttribute('href')).toBe(
            `${ORIGIN}/g/7KQ2MX`,
        );

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(box.hidden).toBe(true);

        h.destroy();
        expect(document.querySelector(`.${MARK_CLASS}`)).toBeNull();
        expect(document.getElementById('t')?.textContent).toContain(
            'Die SL und',
        );
    });

    it('nur erste Fundstelle und Obergrenze', () => {
        document.body.innerHTML = '<p>SL SL SL LP</p>';
        const h = new Highlighter(ENTRIES, { origin: ORIGIN, firstOnly: true });
        expect(h.scan(document.body)).toBe(2);
        h.destroy();
        const limited = new Highlighter(ENTRIES, {
            origin: ORIGIN,
            maxMatches: 1,
        });
        expect(limited.scan(document.body)).toBe(1);
        limited.destroy();
    });
});

describe('loadIndex', () => {
    it('nutzt ETag und den gespeicherten Stand', async () => {
        const memory = new Map<string, string>();
        const store = {
            get: async (k: string) => memory.get(k) ?? null,
            set: async (k: string, v: string) => void memory.set(k, v),
        };
        let calls = 0;
        const fetcher = (async (_url: string, init?: RequestInit) => {
            calls++;
            const etag = ((init?.headers ?? {}) as Record<string, string>)[
                'If-None-Match'
            ];
            if (etag === '"a"') {
                return new Response(null, { status: 304 });
            }

            return new Response(
                JSON.stringify({ release: 1, terms: ENTRIES }),
                { status: 200, headers: { ETag: '"a"' } },
            );
        }) as typeof fetch;

        expect((await loadIndex(ORIGIN, store, fetcher)).terms).toHaveLength(4);
        expect((await loadIndex(ORIGIN, store, fetcher)).terms).toHaveLength(4);
        expect(calls).toBe(1);

        const raw = JSON.parse(memory.get('gls-index-v1') ?? '{}');
        raw.at = 0;
        memory.set('gls-index-v1', JSON.stringify(raw));
        expect((await loadIndex(ORIGIN, store, fetcher)).release).toBe(1);
        expect(calls).toBe(2);
    });
});
