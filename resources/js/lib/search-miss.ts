/**
 * Suche ohne Treffer melden (#285): nur der Suchtext, ohne Personenbezug; je Seitenaufruf und Text einmal.
 * Fehler werden ignoriert – die Meldung ist ein Hinweis für die Redaktion, keine Funktion für die Person.
 */
const gemeldet = new Set<string>();

export function meldeSucheOhneTreffer(text: string): void {
    const q = text.trim().toLowerCase().replace(/\s+/g, ' ');
    if (q.length < 2 || q.length > 100 || gemeldet.has(q)) {
        return;
    }
    gemeldet.add(q);
    const token = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    void fetch('/suche/ohne-treffer', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
        },
        body: JSON.stringify({ q }),
        credentials: 'same-origin',
        keepalive: true,
    }).catch(() => undefined);
}
