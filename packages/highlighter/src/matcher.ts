import type { HighlightOptions, IndexEntry, Match } from './types';

/** Mindestlänge: kürzere Abkürzungen (ein Zeichen) wären zu oft falsch. */
const MIN_ABBREVIATION = 2;

/** Bezeichnungen und Schreibweisen erst ab dieser Länge (sonst zu viele Zufallstreffer). */
const MIN_NAME = 4;

/** Höchstens so viele Muster je regulärem Ausdruck (Browser-Grenzen). */
const CHUNK = 800;

const escape = (s: string): string => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/** Wortgrenze für Unicode (Umlaute, Ziffern): kein Buchstabe und keine Ziffer direkt davor bzw. danach. */
const wrap = (alternation: string, flags: string): RegExp =>
    new RegExp(
        `(?<![\\p{L}\\p{N}])(?:${alternation})(?![\\p{L}\\p{N}])`,
        flags,
    );

/**
 * Erkennt Begriffe im Text (DEC-026): Abkürzungen mit exakter Gross-/Kleinschreibung («SL», nicht «sl» in
 * «Slogan»), Bezeichnungen und Schreibweisen ohne Gross-/Kleinschreibung. Längster Treffer gewinnt; überlappende
 * Treffer werden verworfen.
 */
export class Matcher {
    private readonly exact = new Map<string, IndexEntry[]>();

    private readonly folded = new Map<string, IndexEntry[]>();

    private readonly patterns: RegExp[] = [];

    constructor(
        entries: IndexEntry[],
        options: Pick<HighlightOptions, 'names' | 'categories'> = {},
    ) {
        const categories = options.categories ?? [];
        for (const entry of entries) {
            if (
                categories.length > 0 &&
                !entry.categories.some((c) => categories.includes(c))
            ) {
                continue;
            }
            const abbreviation = (entry.abbreviation ?? '').trim();
            if (abbreviation.length >= MIN_ABBREVIATION) {
                add(this.exact, abbreviation, entry);
            }
            if (options.names) {
                for (const word of [entry.name ?? '', ...entry.aliases]) {
                    const w = word.trim();
                    if (w.length >= MIN_NAME && w !== abbreviation) {
                        add(this.folded, w.toLocaleLowerCase('de-CH'), entry);
                    }
                }
            }
        }
        this.patterns = [
            ...build([...this.exact.keys()], 'gu'),
            ...build([...this.folded.keys()], 'giu'),
        ];
    }

    get size(): number {
        return this.exact.size + this.folded.size;
    }

    /** Treffer in einem Text, nach Position, ohne Überlappungen. */
    find(text: string): Match[] {
        const candidates: Match[] = [];
        for (const pattern of this.patterns) {
            pattern.lastIndex = 0;
            for (
                let m = pattern.exec(text);
                m !== null;
                m = pattern.exec(text)
            ) {
                const found = m[0];
                const entries =
                    this.exact.get(found) ??
                    this.folded.get(found.toLocaleLowerCase('de-CH')) ??
                    [];
                if (entries.length > 0) {
                    candidates.push({
                        start: m.index,
                        end: m.index + found.length,
                        text: found,
                        entries,
                    });
                }
            }
        }
        candidates.sort(
            (a, b) => a.start - b.start || b.end - b.start - (a.end - a.start),
        );
        const result: Match[] = [];
        let until = -1;
        for (const c of candidates) {
            if (c.start >= until) {
                result.push(c);
                until = c.end;
            }
        }

        return result;
    }
}

function add(
    map: Map<string, IndexEntry[]>,
    key: string,
    entry: IndexEntry,
): void {
    const list = map.get(key);
    if (list === undefined) {
        map.set(key, [entry]);
    } else if (!list.includes(entry)) {
        list.push(entry);
    }
}

/** Längste Muster zuerst, in Abschnitten. */
function build(keys: string[], flags: string): RegExp[] {
    const sorted = [...keys].sort((a, b) => b.length - a.length);
    const out: RegExp[] = [];
    for (let i = 0; i < sorted.length; i += CHUNK) {
        out.push(
            wrap(
                sorted
                    .slice(i, i + CHUNK)
                    .map(escape)
                    .join('|'),
                flags,
            ),
        );
    }

    return out;
}
