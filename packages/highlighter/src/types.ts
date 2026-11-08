/** Eintrag des kompakten Index (GET /api/v1/index, #159). */
export type IndexEntry = {
    code: string | null;
    abbreviation: string | null;
    name: string | null;
    aliases: string[];
    short_description: string | null;
    is_ambiguous: boolean;
    categories: string[];
    permalink: string | null;
};

export type IndexResponse = {
    release: number;
    license?: { name?: string; url?: string; attribution?: string };
    terms: IndexEntry[];
};

export type HighlightOptions = {
    /** Ursprung des Glossary, z. B. https://glossary.eduksx.ch – nur dorthin führen Links. */
    origin: string;
    /** Auch ausgeschriebene Bezeichnungen und Schreibweisen erkennen (sonst nur Abkürzungen). */
    names?: boolean;
    /** Je Begriff nur die erste Fundstelle markieren. */
    firstOnly?: boolean;
    /** Höchstens so viele Markierungen je Durchgang (Leistung). */
    maxMatches?: number;
    /** Nur Begriffe dieser Kategorien (Labels); leer = alle. */
    categories?: string[];
};

/** Ein Treffer im Text: Position und alle passenden Einträge (mehrdeutig = mehrere). */
export type Match = {
    start: number;
    end: number;
    text: string;
    entries: IndexEntry[];
};
