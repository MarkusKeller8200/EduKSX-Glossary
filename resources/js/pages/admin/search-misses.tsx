import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type Eintrag = {
    id: number;
    suche: string;
    anzahl: number;
    zuerst: string;
    zuletzt: string;
};

/**
 * Suchen ohne Treffer (#285): Hinweise auf fehlende Abkürzungen. Gespeichert ist nur der Suchtext mit Zähler,
 * ohne Person; nach der Frist ohne neue Suche wird der Eintrag gelöscht.
 */
export default function SearchMisses({
    eintraege,
    tage,
}: {
    eintraege: Eintrag[];
    tage: number;
}) {
    const datum = (iso: string) => iso.split('-').reverse().join('.');

    return (
        <>
            <Head title="Suchen ohne Treffer" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Suchen ohne Treffer
            </h1>
            <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                Suchtexte auf der Website und in der API, die keinen Begriff
                gefunden haben – meistgesucht zuerst. Ohne Personenbezug;
                Einträge ohne neue Suche werden nach {tage} Tagen gelöscht.
            </p>

            {eintraege.length === 0 ? (
                <p className="mt-6 text-sm text-muted-foreground">
                    Keine Einträge.
                </p>
            ) : (
                <table className="mt-6 w-full max-w-4xl text-sm">
                    <thead>
                        <tr className="border-b border-border text-left">
                            <th className="py-2 pr-4 font-medium">Suche</th>
                            <th className="py-2 pr-4 text-right font-medium">
                                Anzahl
                            </th>
                            <th className="py-2 pr-4 font-medium">Zuerst</th>
                            <th className="py-2 pr-4 font-medium">Zuletzt</th>
                            <th className="py-2 font-medium">
                                <span className="sr-only">Aktionen</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {eintraege.map((e) => (
                            <tr
                                key={e.id}
                                className="border-b border-border/60"
                            >
                                <td className="py-2 pr-4" translate="no">
                                    {e.suche}
                                </td>
                                <td className="py-2 pr-4 text-right tabular-nums">
                                    {e.anzahl}
                                </td>
                                <td className="py-2 pr-4">{datum(e.zuerst)}</td>
                                <td className="py-2 pr-4">
                                    {datum(e.zuletzt)}
                                </td>
                                <td className="flex flex-wrap justify-end gap-2 py-2">
                                    <Button asChild size="sm" variant="outline">
                                        <Link href="/admin/begriffe/neu">
                                            Begriff anlegen
                                        </Link>
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={`«${e.suche}» als erledigt entfernen`}
                                        onClick={() =>
                                            router.delete(
                                                `/admin/suchen-ohne-treffer/${e.id}`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Erledigt
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </>
    );
}
