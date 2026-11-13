import { Head, router, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

type Eintrag = {
    art: string;
    art_label: string;
    id: string;
    name: string;
    geloescht_am: string | null;
    von: string | null;
    endgueltig_am: string | null;
};

/**
 * Papierkorb (#286): vorläufig gelöschte Kategorien, Herkünfte und Organisationen. Wiederherstellen nur Developer;
 * nach der Frist löscht die nächtliche Aufgabe endgültig.
 */
export default function Trash({
    eintraege,
    tage,
}: {
    eintraege: Eintrag[];
    tage: number;
}) {
    const errors = usePage().props.errors as Record<string, string>;
    const datum = (iso: string | null) =>
        iso ? iso.split('-').reverse().join('.') : '–';

    return (
        <>
            <Head title="Papierkorb" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Papierkorb
            </h1>
            <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                Gelöschte Kategorien, Herkünfte und Organisationen bleiben{' '}
                {tage} Tage wiederherstellbar und werden danach endgültig
                gelöscht. Eine Organisation kommt mit Name und Darstellung
                zurück, ohne Mitglieder; wer sie gelöscht hat, wird wieder
                Organisations-Admin.
            </p>
            <InputError className="mt-4" message={errors.papierkorb} />

            {eintraege.length === 0 ? (
                <p className="mt-6 text-sm text-muted-foreground">
                    Der Papierkorb ist leer.
                </p>
            ) : (
                <table className="mt-6 w-full max-w-5xl text-sm">
                    <thead>
                        <tr className="border-b border-border text-left">
                            <th className="py-2 pr-4 font-medium">Art</th>
                            <th className="py-2 pr-4 font-medium">Name</th>
                            <th className="py-2 pr-4 font-medium">Gelöscht</th>
                            <th className="py-2 pr-4 font-medium">Von</th>
                            <th className="py-2 pr-4 font-medium">
                                Endgültig ab
                            </th>
                            <th className="py-2 font-medium">
                                <span className="sr-only">Aktionen</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {eintraege.map((e) => (
                            <tr
                                key={`${e.art}-${e.id}`}
                                className="border-b border-border/60"
                            >
                                <td className="py-2 pr-4">{e.art_label}</td>
                                <td className="py-2 pr-4">{e.name}</td>
                                <td className="py-2 pr-4">
                                    {datum(e.geloescht_am)}
                                </td>
                                <td className="py-2 pr-4">{e.von ?? '–'}</td>
                                <td className="py-2 pr-4">
                                    {datum(e.endgueltig_am)}
                                </td>
                                <td className="py-2 text-right">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                `/admin/papierkorb/${e.art}/${e.id}/wiederherstellen`,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Wiederherstellen
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
