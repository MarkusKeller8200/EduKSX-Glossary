export type AppVersion = {
    version: string;
    build: string | null;
    build_date: string | null;
    prototype: boolean;
    dev: {
        commits: number;
        base: string;
        planned: string;
        label: string;
    } | null;
};

export type AuthUser = {
    id: string;
    name: string;
    email: string;
};

/** Daten, die jede Seite erhält (HandleInertiaRequests::share). */
export type SharedData = {
    name: string;
    auth: {
        user: AuthUser | null;
        /** Rechte des Kontos (#36); nur für die Anzeige */
        can: Record<string, true>;
        roles: string[];
        /** einmal nach der Anmeldung nach News fragen (#169) */
        newsFrage: boolean;
    };
    appVersion: AppVersion;
    links: {
        githubRepo: string | null;
        pages: string | null;
        quellcode: string;
    };
    flash: { status: string | null };
    /** Farbe (Name der Palette) je Label der Kategorien und Herkunft (#77) */
    farben: {
        kategorien: Record<string, string>;
        herkunft: Record<string, string>;
    };
    /** Bezeichnung, Beschreibung und Link je Label (#258) */
    labelInfo: Record<
        'kategorien' | 'herkunft',
        Record<string, { name: string; beschreibung: string; url: string }>
    >;
};

export type Release = {
    version: string;
    date: string | null;
    sections: { title: string; items: string[] }[];
};
