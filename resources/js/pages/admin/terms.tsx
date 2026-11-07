import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

type Begriff = {
    id: string;
    abkuerzung: string;
    bezeichnung: string;
    kategorien: string[];
    status: string;
    aktiv: boolean;
    revision: number;
    veroeffentlicht: number | null;
};

/** Begriffe (Arbeitsstand). Bearbeiten in der Tabelle wie im Intranet folgt mit #14. */
export default function Terms({ begriffe }: { begriffe: Begriff[] }) {
    const [suche, setSuche] = useState('');
    const q = suche.trim().toLowerCase();
    const liste = q
        ? begriffe.filter((b) =>
              `${b.abkuerzung} ${b.bezeichnung} ${b.kategorien.join(' ')}`
                  .toLowerCase()
                  .includes(q),
          )
        : begriffe;
    const [auswahl, setAuswahl] = useState<Set<string>>(new Set());
    const [sending, setSending] = useState(false);
    const alleGewaehlt =
        liste.length > 0 && liste.every((b) => auswahl.has(b.id));
    const umschalten = (id: string, an: boolean) =>
        setAuswahl((alt) => {
            const neu = new Set(alt);
            if (an) {
                neu.add(id);
            } else {
                neu.delete(id);
            }
            return neu;
        });
    const alleUmschalten = (an: boolean) =>
        setAuswahl((alt) => {
            const neu = new Set(alt);
            liste.forEach((b) => (an ? neu.add(b.id) : neu.delete(b.id)));
            return neu;
        });
    const entwuerfeWaehlen = () =>
        setAuswahl(
            new Set(
                liste
                    .filter((b) => b.veroeffentlicht !== b.revision)
                    .map((b) => b.id),
            ),
        );
    const veroeffentlichen = () => {
        setSending(true);
        router.post(
            '/admin/begriffe/veroeffentlichen',
            { ids: [...auswahl] },
            {
                preserveScroll: true,
                onSuccess: () => setAuswahl(new Set()),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <>
            <Head title="Begriffe" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Begriffe
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {begriffe.length} Begriffe, davon{' '}
                        {begriffe.filter((b) => b.veroeffentlicht).length}{' '}
                        veröffentlicht. Neue und geänderte Begriffe sind
                        Entwürfe, bis sie freigegeben werden.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <Link href="/admin/doppelte">Doppelte prüfen</Link>
                    </Button>
                    <Button asChild variant="outline">
                        <a href="/admin/export">Excel-Export</a>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/admin/import">Excel-Import</Link>
                    </Button>
                    <Button asChild>
                        <Link href="/admin/begriffe/neu">Neuer Begriff</Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 flex flex-wrap items-center gap-3">
                <div className="w-full max-w-sm">
                    <label htmlFor="suche" className="sr-only">
                        Begriffe filtern
                    </label>
                    <Input
                        id="suche"
                        type="search"
                        placeholder="Filtern …"
                        value={suche}
                        onChange={(e) => setSuche(e.target.value)}
                    />
                </div>
                <Button variant="outline" onClick={entwuerfeWaehlen}>
                    Alle Entwürfe auswählen
                </Button>
                <Button
                    onClick={veroeffentlichen}
                    disabled={auswahl.size === 0 || sending}
                >
                    Ausgewählte veröffentlichen ({auswahl.size})
                </Button>
            </div>

            <div className="mt-4 overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted text-left">
                        <tr>
                            <th scope="col" className="w-10 px-3 py-2">
                                <Checkbox
                                    aria-label="Alle gefilterten auswählen"
                                    checked={alleGewaehlt}
                                    onCheckedChange={(v) =>
                                        alleUmschalten(v === true)
                                    }
                                />
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Abkürzung
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Bezeichnung
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Kategorien
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Status
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Veröffentlicht
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {liste.map((b) => (
                            <tr
                                key={b.id}
                                className="border-t border-border align-top"
                            >
                                <td className="px-3 py-2">
                                    <Checkbox
                                        aria-label={`${b.abkuerzung || b.bezeichnung} auswählen`}
                                        checked={auswahl.has(b.id)}
                                        onCheckedChange={(v) =>
                                            umschalten(b.id, v === true)
                                        }
                                    />
                                </td>
                                <td
                                    className="px-3 py-2 font-medium"
                                    translate="no"
                                >
                                    <Link
                                        href={`/admin/begriffe/${b.id}`}
                                        className="text-link underline-offset-4 hover:underline"
                                    >
                                        {b.abkuerzung || '–'}
                                    </Link>
                                </td>
                                <td className="px-3 py-2" translate="no">
                                    {b.bezeichnung}
                                    {!b.aktiv ? (
                                        <span className="ml-2 text-muted-foreground">
                                            (inaktiv)
                                        </span>
                                    ) : null}
                                </td>
                                <td className="px-3 py-2">
                                    <div className="flex flex-wrap gap-1">
                                        {b.kategorien.map((k) => (
                                            <Badge key={k} variant="outline">
                                                {k}
                                            </Badge>
                                        ))}
                                    </div>
                                </td>
                                <td className="px-3 py-2">{b.status}</td>
                                <td className="px-3 py-2">
                                    {b.veroeffentlicht === null ? (
                                        <Badge variant="secondary">
                                            Entwurf
                                        </Badge>
                                    ) : b.veroeffentlicht === b.revision ? (
                                        `Revision ${b.revision}`
                                    ) : (
                                        `Revision ${b.veroeffentlicht} (Entwurf ${b.revision})`
                                    )}
                                </td>
                            </tr>
                        ))}
                        {liste.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-3 py-6 text-center text-muted-foreground"
                                >
                                    {begriffe.length === 0
                                        ? 'Noch keine Begriffe – mit dem Excel-Import übernehmen.'
                                        : 'Keine Treffer.'}
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>
        </>
    );
}
