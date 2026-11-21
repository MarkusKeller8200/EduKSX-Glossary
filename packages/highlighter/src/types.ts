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
    /** Recherche (#479): ai_assisted, human_reviewed oder fehlt/null */
    research_basis?: string | null;
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
    /**
     * Mit firstOnly (#474): Begriffe wieder freigeben, deren Markierung nicht mehr im Dokument ist (Ansichtswechsel in
     * Single-Page-Apps). Vorgabe aus (Verhalten wie 1.0.0); die Browser-Erweiterung schaltet es ein.
     */
    releaseDetached?: boolean;
    /** «Erklärung falsch?» in der Erklärbox (#480); ohne Angabe kein Knopf. */
    onReport?: (entry: IndexEntry) => void;
    /** Herkunft der Links «Im Glossary öffnen» für die Statistik ohne Cookies (#495) */
    via?: 'erweiterung' | 'einbindung';
    /** Höchstens so viele Markierungen je Durchgang (Leistung). */
    maxMatches?: number;
    /** Nur Begriffe dieser Kategorien (Labels); leer = alle. */
    categories?: string[];
};

/** Optionen eines einzelnen Durchgangs (#474). */
export type ScanOptions = {
    /** Erste Fundstelle je Begriff innerhalb dieses Durchgangs (Ansicht), unabhängig von früheren Durchgängen. */
    firstOnly?: boolean;
};

/** Ein Treffer im Text: Position und alle passenden Einträge (mehrdeutig = mehrere). */
export type Match = {
    start: number;
    end: number;
    text: string;
    entries: IndexEntry[];
};
