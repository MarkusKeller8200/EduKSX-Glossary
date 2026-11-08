import { Head, Link } from '@inertiajs/react';
import DataGrid from '@/components/data-grid';
import IconAction from '@/components/icon-action';
import { Pencil } from 'lucide-react';

type Zeile = {
    id: string;
    titel: string;
    gruende: string[];
    aeltestes: string | null;
};

/** Quellenprüfung (#69): Begriffe zur jährlichen Wiedervorlage. */
export default function SourceCheck({
    begriffe,
    monate,
}: {
    begriffe: Zeile[];
    monate: number;
}) {
    return (
        <>
            <Head title="Prüfung" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Prüfung der Quellen
            </h1>
            <p className="mt-2 text-sm text-muted-foreground">
                {begriffe.length === 1
                    ? '1 Begriff'
                    : `${begriffe.length} Begriffe`}{' '}
                zur Wiedervorlage: Herkunft nie oder vor mehr als {monate}{' '}
                Monaten geprüft, Status «zu prüfen», ohne Herkunft oder mit
                offizieller Form ohne amtlichen Beleg. Prüfdatum und Belegstatus
                je Herkunft auf der Seite des Begriffs eintragen.
            </p>
            <div className="mt-6">
                <DataGrid
                    id="admin.source-check"
                    caption="Begriffe zur Wiedervorlage"
                    rows={begriffe}
                    getRowId={(b: Zeile) => b.id}
                    empty="Alles geprüft."
                    columns={[
                        {
                            id: 'begriff',
                            header: 'Begriff',
                            width: 260,
                            sticky: 'left',
                            noTranslate: true,
                            sortValue: (b) => b.titel,
                            cell: (b) => (
                                <Link
                                    href={`/admin/begriffe/${b.id}#herkunft`}
                                    className="text-link underline-offset-4 hover:underline"
                                >
                                    {b.titel || '(ohne Bezeichnung)'}
                                </Link>
                            ),
                        },
                        {
                            id: 'gruende',
                            header: 'Gründe',
                            width: 420,
                            sortValue: (b) => b.gruende.length,
                            cell: (b) => (
                                <ul className="list-disc pl-4">
                                    {b.gruende.map((g) => (
                                        <li key={g}>{g}</li>
                                    ))}
                                </ul>
                            ),
                        },
                        {
                            id: 'aeltestes',
                            header: 'Ältestes Prüfdatum',
                            width: 160,
                            sortValue: (b) => b.aeltestes ?? '',
                            cell: (b) =>
                                b.aeltestes
                                    ? new Date(b.aeltestes).toLocaleDateString(
                                          'de-CH',
                                      )
                                    : 'nie',
                        },
                        {
                            id: 'aktionen',
                            label: 'Aktionen',
                            header: <span className="sr-only">Aktionen</span>,
                            width: 56,
                            fixed: true,
                            sticky: 'right',
                            className: 'py-1',
                            cell: (b) => (
                                <IconAction
                                    icon={Pencil}
                                    label={`${b.titel || 'Begriff'}: Herkunft prüfen`}
                                    href={`/admin/begriffe/${b.id}#herkunft`}
                                />
                            ),
                        },
                    ]}
                />
            </div>
        </>
    );
}
