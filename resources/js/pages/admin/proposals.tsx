import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { proposalStatusClass } from '@/lib/proposals';
import DataGrid from '@/components/data-grid';
import IconAction from '@/components/icon-action';
import { Eye } from 'lucide-react';

type Vorschlag = {
    id: string;
    nummer: number;
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
                <div className="mt-6">
                    <DataGrid
                        id="admin.proposals"
                        caption="Vorschläge"
                        rows={vorschlaege}
                        getRowId={(v: Vorschlag) => String(v.id)}
                        columns={[
                            {
                                id: 'nr',
                                header: 'Nr.',
                                width: 70,
                                sticky: 'left',
                                className: 'tabular-nums',
                                sortValue: (v) => v.nummer,
                                cell: (v) => (
                                    <Link
                                        href={`/admin/vorschlaege/${v.id}`}
                                        className="font-medium text-link underline-offset-4 hover:underline"
                                    >
                                        {v.nummer}
                                    </Link>
                                ),
                            },
                            {
                                id: 'begriff',
                                header: 'Begriff',
                                width: 240,
                                noTranslate: true,
                                sortValue: (v) => v.titel,
                                cell: (v) => (
                                    <>
                                        <span className="block text-xs text-muted-foreground">
                                            {v.art}
                                        </span>
                                        {v.titel || '–'}
                                    </>
                                ),
                            },
                            {
                                id: 'felder',
                                header: 'Felder',
                                width: 200,
                                sortValue: (v) => v.felder.join(', '),
                                cell: (v) => v.felder.join(', '),
                            },
                            {
                                id: 'von',
                                header: 'Von',
                                width: 160,
                                sortValue: (v) => v.von,
                                cell: (v) => v.von,
                            },
                            {
                                id: 'eingereicht',
                                header: 'Eingereicht',
                                width: 120,
                                sortValue: (v) => v.eingereicht ?? '',
                                cell: (v) =>
                                    v.eingereicht
                                        ? new Date(
                                              v.eingereicht,
                                          ).toLocaleDateString('de-CH')
                                        : '–',
                            },
                            {
                                id: 'status',
                                header: 'Status',
                                width: 130,
                                sortValue: (v) => v.status_label,
                                cell: (v) => (
                                    <Badge
                                        variant="outline"
                                        className={proposalStatusClass(
                                            v.status,
                                        )}
                                    >
                                        {v.status_label}
                                    </Badge>
                                ),
                            },
                            {
                                id: 'aktionen',
                                label: 'Aktionen',
                                header: (
                                    <span className="sr-only">Aktionen</span>
                                ),
                                width: 56,
                                fixed: true,
                                sticky: 'right',
                                className: 'py-1',
                                cell: (v) => (
                                    <IconAction
                                        icon={Eye}
                                        label={`Vorschlag Nr. ${v.nummer} prüfen`}
                                        href={`/admin/vorschlaege/${v.id}`}
                                    />
                                ),
                            },
                        ]}
                    />
                </div>
            )}
        </>
    );
}
