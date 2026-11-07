import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <a href="/admin/export">Excel-Export</a>
                    </Button>
                    <Button asChild>
                        <Link href="/admin/import">Excel-Import</Link>
                    </Button>
                </div>
            </div>

            <div className="mt-6 max-w-sm">
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

            <div className="mt-4 overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted text-left">
                        <tr>
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
                                <td
                                    className="px-3 py-2 font-medium"
                                    translate="no"
                                >
                                    {b.abkuerzung}
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
                                    colSpan={5}
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
