import { router } from '@inertiajs/react';
import { confirmAction } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';

/** Hinweise pro Konto: true = ausgeblendet, false = aktiv, null = gilt nicht (#469). */
export type KontoHinweise = {
    moderation: boolean | null;
    news: boolean | null;
    domain: boolean | null;
    datenschutz: boolean | null;
};

type Browser = 'cookie' | 'update' | 'app';

const KONTO: [keyof KontoHinweise, string][] = [
    ['moderation', 'Mithelfen als Moderator/in'],
    ['news', 'Frage nach News per E-Mail'],
    ['domain', 'In die Organisation aufgenommen'],
    ['datenschutz', 'Kenntnisnahme der Datenschutzerklärung (zum Testen)'],
];

const BROWSER: [Browser, string][] = [
    ['cookie', 'Cookie-Hinweis'],
    ['update', '«Aktualisiert auf Version …»'],
    ['app', '«Als App installieren» (Handy)'],
];

const lies = (key: string): string | null => {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
};

/** ausgeblendet? (gleiche Schlüssel wie die Hinweise selbst) */
const browserAus = (b: Browser): boolean =>
    b === 'cookie'
        ? lies('cookie-notice') !== null
        : b === 'update'
          ? lies('glossary.version') !== 'zurueckgesetzt'
          : lies('glossary.app-hinweis') === 'aus';

const browserAn = (b: Browser) => {
    try {
        if (b === 'cookie') {
            window.localStorage.removeItem('cookie-notice');
        } else if (b === 'update') {
            // weicht vom aktuellen Stand ab → der Hinweis erscheint beim nächsten Laden
            window.localStorage.setItem('glossary.version', 'zurueckgesetzt');
        } else {
            window.localStorage.removeItem('glossary.app-hinweis');
        }
    } catch {
        // ohne Speicher im Browser nichts zu tun
    }
};

/**
 * Ausgeblendete Hinweise wieder anzeigen (#469) und alles löschen, was der Browser für das Glossary gemerkt hat. Das
 * Anmelde-Cookie und der Schutz vor gefälschten Formularen bleiben (technisch nötig, «Abmelden» entfernt sie).
 */
export default function NoticesPanel({
    hinweise,
}: {
    hinweise: KontoHinweise;
}) {
    const opts = { preserveScroll: true } as const;
    const konto = KONTO.filter(([k]) => hinweise[k] !== null);
    const allesWieder = () => {
        BROWSER.forEach(([b]) => browserAn(b));
        router.post(
            '/konto/hinweise',
            { hinweis: 'alle' },
            {
                ...opts,
                onSuccess: () => window.location.reload(),
            },
        );
    };
    const loeschen = async () => {
        if (
            !(await confirmAction(
                'Alles löschen, was dieser Browser für das Glossary gemerkt hat (Ansicht, Details, Seitenbreite, Hell/Dunkel, Spaltenbreiten und Sortierung, Gruppierung, PDF-Einstellungen, ausgeblendete Hinweise)? Sie bleiben angemeldet.',
                { confirmLabel: 'Löschen' },
            ))
        ) {
            return;
        }
        try {
            const keys: string[] = [];
            for (let i = 0; i < window.localStorage.length; i++) {
                const k = window.localStorage.key(i);
                if (
                    k &&
                    (k.startsWith('glossary.') ||
                        ['cookie-notice', 'view-width', 'appearance'].includes(
                            k,
                        ))
                ) {
                    keys.push(k);
                }
            }
            keys.forEach((k) => window.localStorage.removeItem(k));
        } catch {
            // ohne Speicher im Browser nichts zu löschen
        }
        // eigenes Cookie für Hell/Dunkel; Anmelde- und Formular-Cookies bleiben
        document.cookie = 'appearance=;path=/;max-age=0;SameSite=Lax';
        window.location.reload();
    };

    return (
        <section className="mt-8 max-w-2xl border-t border-border pt-6">
            <h2 className="text-lg font-semibold">Hinweise</h2>
            <p className="mt-3 text-sm text-muted-foreground">
                Weggeklickte oder einmalige Hinweise wieder anzeigen. Hinweise
                «in diesem Browser» gelten nur für dieses Gerät.
            </p>
            <ul className="mt-3 divide-y rounded-lg border border-border text-sm">
                {konto.map(([k, text]) => (
                    <Zeile
                        key={k}
                        text={text}
                        aus={hinweise[k] === true}
                        onAn={() =>
                            router.post('/konto/hinweise', { hinweis: k }, opts)
                        }
                    />
                ))}
                {BROWSER.map(([b, text]) => (
                    <Zeile
                        key={b}
                        text={`${text} – in diesem Browser`}
                        aus={browserAus(b)}
                        onAn={() => {
                            browserAn(b);
                            window.location.reload();
                        }}
                    />
                ))}
            </ul>
            <div className="mt-3 flex flex-wrap gap-2">
                <Button variant="outline" onClick={allesWieder}>
                    Alle Hinweise wieder anzeigen
                </Button>
                <Button variant="outline" onClick={loeschen}>
                    Ansichten und Cookies löschen
                </Button>
            </div>
        </section>
    );
}

function Zeile({
    text,
    aus,
    onAn,
}: {
    text: string;
    aus: boolean;
    onAn: () => void;
}) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
            <span>
                {text}{' '}
                <span className="text-muted-foreground">
                    ({aus ? 'ausgeblendet' : 'aktiv'})
                </span>
            </span>
            <Button size="sm" variant="ghost" disabled={!aus} onClick={onAn}>
                Wieder anzeigen
            </Button>
        </li>
    );
}
