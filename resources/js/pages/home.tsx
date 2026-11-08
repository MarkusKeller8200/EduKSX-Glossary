import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import ExportMenu from '@/components/export-menu';
import GlossaryPdfDialog from '@/components/glossary-pdf-dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { ListBadges, StatusBadge } from '@/components/tags';

type Begriff = {
    slug: string;
    abkuerzung: string;
    bezeichnung: string;
    kurz: string;
    kategorien: string[];
    status: string;
    suche: string;
};

const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');

/** Anfangsbuchstabe für den Index (Umlaute zum Grundbuchstaben, Ziffern unter «#»). */
function initial(b: Begriff): string {
    const first = (b.abkuerzung || b.bezeichnung)
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .charAt(0)
        .toUpperCase();

    return ALPHABET.includes(first) ? first : '#';
}

/** Startseite: veröffentlichte Begriffe mit Suche, Index A–Z und Kategorien (#24, PUB-01/02). */
export default function Home({ begriffe }: { begriffe: Begriff[] }) {
    const { auth } = usePage().props;
    const [suche, setSuche] = useState('');
    const [pdf, setPdf] = useState(false);
    const [buchstabe, setBuchstabe] = useState<string | null>(null);
    const [kategorie, setKategorie] = useState<string | null>(null);

    const kategorien = useMemo(
        () =>
            [...new Set(begriffe.flatMap((b) => b.kategorien))].sort((a, b) =>
                a.localeCompare(b, 'de-CH'),
            ),
        [begriffe],
    );
    const vorhanden = useMemo(() => new Set(begriffe.map(initial)), [begriffe]);

    const q = suche.trim().toLowerCase();
    const liste = begriffe.filter(
        (b) =>
            (q === '' || b.suche.includes(q)) &&
            (buchstabe === null || initial(b) === buchstabe) &&
            (kategorie === null || b.kategorien.includes(kategorie)),
    );

    return (
        <>
            <Head title="Begriffe und Abkürzungen" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Begriffe und Abkürzungen
            </h1>
            <p className="mt-2 max-w-2xl text-muted-foreground">
                Ein öffentliches Glossar für Schweizer Volksschulen,
                Schulgemeinden und Gemeinden: Abkürzungen und Begriffe mit
                Erklärung, Quelle und Gültigkeit.
            </p>

            <div className="mt-6 flex flex-wrap items-center gap-3">
                <div className="w-full max-w-xl">
                    <label htmlFor="suche" className="sr-only">
                        Begriff suchen
                    </label>
                    <Input
                        id="suche"
                        type="search"
                        placeholder="Begriff oder Abkürzung suchen …"
                        value={suche}
                        onChange={(e) => setSuche(e.target.value)}
                        autoFocus
                    />
                </div>
                {/* Export der sichtbaren Begriffe (#131): ab Recht «export», sonst ausgegraut mit Hinweis */}
                <ExportMenu
                    endpoint="/export"
                    keys={liste.map((b) => b.slug)}
                    allowed={auth.can.export === true}
                    hint={
                        auth.user
                            ? { text: 'Export ab Rolle Benutzer' }
                            : {
                                  text: 'Zum Exportieren anmelden',
                                  href: '/anmelden',
                              }
                    }
                    onPdf={() => setPdf(true)}
                />
            </div>

            <nav
                aria-label="Index A–Z"
                className="mt-4 flex flex-wrap gap-1 text-sm"
            >
                {[...ALPHABET, '#'].map((l) => (
                    <button
                        key={l}
                        type="button"
                        disabled={!vorhanden.has(l)}
                        aria-pressed={buchstabe === l}
                        onClick={() => setBuchstabe(buchstabe === l ? null : l)}
                        className={cn(
                            'min-w-8 rounded-md px-2 py-1 font-medium disabled:text-muted-foreground/50',
                            buchstabe === l
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-accent hover:text-accent-foreground',
                        )}
                    >
                        {l}
                    </button>
                ))}
            </nav>

            {kategorien.length > 0 ? (
                <div
                    className="mt-3 flex flex-wrap gap-2"
                    role="group"
                    aria-label="Kategorie"
                >
                    {kategorien.map((k) => (
                        <button
                            key={k}
                            type="button"
                            aria-pressed={kategorie === k}
                            onClick={() =>
                                setKategorie(kategorie === k ? null : k)
                            }
                            className={cn(
                                'rounded-full border border-border px-3 py-1 text-sm',
                                kategorie === k
                                    ? 'border-transparent bg-secondary font-medium text-secondary-foreground'
                                    : 'hover:bg-accent hover:text-accent-foreground',
                            )}
                        >
                            {k}
                        </button>
                    ))}
                </div>
            ) : null}

            <p
                className="mt-6 text-sm text-muted-foreground"
                aria-live="polite"
            >
                {liste.length === 1 ? '1 Begriff' : `${liste.length} Begriffe`}
                {liste.length !== begriffe.length
                    ? ` von ${begriffe.length}`
                    : ''}
            </p>

            <ul className="mt-2 divide-y divide-border border-y border-border">
                {liste.map((b) => (
                    <li key={b.slug}>
                        <Link
                            href={`/begriffe/${b.slug}`}
                            className="block px-1 py-3 hover:bg-accent/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <span className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                {b.abkuerzung ? (
                                    <span
                                        className="font-semibold"
                                        translate="no"
                                    >
                                        {b.abkuerzung}
                                    </span>
                                ) : null}
                                <span translate="no">{b.bezeichnung}</span>
                                <ListBadges
                                    list="kategorien"
                                    labels={b.kategorien}
                                />
                                {b.status !== 'aktuell' ? (
                                    <StatusBadge status={b.status} />
                                ) : null}
                            </span>
                            {b.kurz ? (
                                <span className="mt-1 block text-sm text-muted-foreground">
                                    {b.kurz}
                                </span>
                            ) : null}
                        </Link>
                    </li>
                ))}
            </ul>

            {begriffe.length === 0 ? (
                <p className="mt-6 text-muted-foreground">
                    Noch keine Begriffe veröffentlicht.
                </p>
            ) : null}

            {pdf ? (
                <GlossaryPdfDialog
                    open={pdf}
                    onOpenChange={setPdf}
                    quelle="/export?format=json"
                    gefiltert={liste.map((b) => b.slug)}
                />
            ) : null}

            <p className="mt-8 text-sm">
                Fehlt ein Begriff?{' '}
                <Link
                    href="/vorschlaege/neu"
                    className="text-link underline underline-offset-4"
                >
                    Begriff vorschlagen
                </Link>
            </p>
        </>
    );
}
