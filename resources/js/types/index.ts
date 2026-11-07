export type AppVersion = {
    version: string;
    build: string | null;
    build_date: string | null;
    prototype: boolean;
    dev: { commits: number; base: string; planned: string } | null;
};

export type AuthUser = {
    id: string;
    name: string;
    email: string;
    is_admin: boolean;
};

/** Daten, die jede Seite erhält (HandleInertiaRequests::share). */
export type SharedData = {
    name: string;
    auth: { user: AuthUser | null };
    appVersion: AppVersion;
    links: { githubRepo: string | null; pages: string | null };
    flash: { status: string | null };
};

export type Release = {
    version: string;
    date: string | null;
    sections: { title: string; items: string[] }[];
};
