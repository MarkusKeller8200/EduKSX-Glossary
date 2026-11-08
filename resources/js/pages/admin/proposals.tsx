import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { proposalStatusClass } from '@/lib/proposals';

type Vorschlag = {
    id: number;
    art: string;
    titel: string;
    felder: string[];
    von: string;
    status: string;
    status_label: string;
    eingereicht: string | null;
};

/** Moderation (#113): offene Vorschläge zuerst, auf Wunsch alle. */
export default function Proposals({
    vorschlaege,
    filter,
}: {
    vorschlaege: Vorschlag[];
    filter: 'offen' | 'alle';
}) {
    return (
        <>
            <Head title="Vorschläge" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <h1 className="text-3xl font-semibold tracking-tight">
                    Vorschläge
                </h1>
                <Button asChild variant="outline">
                    <Link
                        href={
                            filter === 'offen'
                                ? '/admin/vorschlaege?status=alle'
                                : '/admin/vorschlaege'
                        }
                    >
                        {filter === 'offen' ? 'Alle anzeigen' : 'Nur offene'}
                    </Link>
                </Button>
            </div>
            <p className="mt-2 max-w-prose text-sm text-muted-foreground">
                Annehmen erzeugt eine neue Revision als Entwurf; veröffentlicht
                wird getrennt. Felder, die sich seit dem Einreichen geändert
                haben, sind als Konflikt markiert.
            </p>
            {vorschlaege.length === 0 ? (
                <p className="mt-6 text-sm text-muted-foreground">
                    Keine {filter === 'offen' ? 'offenen ' : ''}Vorschläge.
                </p>
            ) : (
                <div className="mt-6 overflow-x-auto rounded-lg border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">Nr.</th>
                                <th className="px-3 py-2 font-medium">
                                    Begriff
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Felder
                                </th>
                                <th className="px-3 py-2 font-medium">Von</th>
                                <th className="px-3 py-2 font-medium">
                                    Eingereicht
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {vorschlaege.map((v) => (
                                <tr
                                    key={v.id}
                                    className="border-t border-border align-top"
                                >
                                    <td className="px-3 py-2">
                                        <Link
                                            href={`/admin/vorschlaege/${v.id}`}
                                            className="font-medium text-link underline-offset-4 hover:underline"
                                        >
                                            {v.id}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">
                                        <span className="block text-xs text-muted-foreground">
                                            {v.art}
                                        </span>
                                        {v.titel || '–'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {v.felder.join(', ')}
                                    </td>
                                    <td className="px-3 py-2">{v.von}</td>
                                    <td className="px-3 py-2">
                                        {v.eingereicht
                                            ? new Date(
                                                  v.eingereicht,
                                              ).toLocaleDateString('de-CH')
                                            : '–'}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant="outline"
                                            className={proposalStatusClass(
                                                v.status,
                                            )}
                                        >
                                            {v.status_label}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </>
    );
}
