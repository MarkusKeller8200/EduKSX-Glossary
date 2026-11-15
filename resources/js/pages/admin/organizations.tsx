import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import DataGrid from '@/components/data-grid';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import UserAdminTabs from '@/components/user-admin-tabs';

type Organisation = {
    id: string;
    name: string;
    mitglieder: number;
    einladungen: number;
    admins: string[];
    microsoft: boolean;
    angelegt: string | null;
};

/** Organisationen in der Administration (#413): Liste neben «Benutzer», Detail nur lesend. */
export default function Organizations({
    organisationen,
}: {
    organisationen: Organisation[];
}) {
    const [suche, setSuche] = useState('');
    const q = suche.trim().toLowerCase();
    const liste = organisationen.filter(
        (o) =>
            !q || `${o.name} ${o.admins.join(' ')}`.toLowerCase().includes(q),
    );

    return (
        <>
            <Head title="Organisationen" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Benutzer und Organisationen
            </h1>
            <UserAdminTabs aktiv="organisationen" />
            <p className="mt-4 text-sm text-muted-foreground">
                {organisationen.length} Organisationen. Verwaltet werden sie von
                ihren Organisations-Admins unter «Meine Organisation»; hier nur
                lesend.
            </p>

            <div className="mt-6 w-full max-w-sm">
                <label htmlFor="suche" className="sr-only">
                    Organisationen filtern
                </label>
                <Input
                    id="suche"
                    type="search"
                    placeholder="Name oder Organisations-Admin …"
                    value={suche}
                    onChange={(e) => setSuche(e.target.value)}
                />
            </div>

            <div className="mt-4">
                <DataGrid
                    id="admin.organizations"
                    caption="Organisationen"
                    rows={liste}
                    getRowId={(o: Organisation) => o.id}
                    empty="Keine Organisationen."
                    initialSort={{ id: 'name', desc: false }}
                    columns={[
                        {
                            id: 'name',
                            header: 'Name',
                            width: 260,
                            sticky: 'left',
                            className: 'font-medium',
                            sortValue: (o) => o.name,
                            cell: (o) => (
                                <Link
                                    href={`/admin/organisationen/${o.id}`}
                                    className="text-link underline-offset-4 hover:underline"
                                >
                                    {o.name}
                                </Link>
                            ),
                        },
                        {
                            id: 'mitglieder',
                            header: 'Mitglieder',
                            width: 110,
                            sortValue: (o) => o.mitglieder,
                            cell: (o) => o.mitglieder,
                        },
                        {
                            id: 'admins',
                            header: 'Organisations-Admins',
                            width: 260,
                            sortValue: (o) => o.admins.join(', '),
                            cell: (o) =>
                                o.admins.length > 0 ? (
                                    o.admins.join(', ')
                                ) : (
                                    <span className="text-muted-foreground">
                                        keine
                                    </span>
                                ),
                        },
                        {
                            id: 'einladungen',
                            header: 'Offene Einladungen',
                            width: 150,
                            sortValue: (o) => o.einladungen,
                            cell: (o) => o.einladungen,
                        },
                        {
                            id: 'microsoft',
                            header: 'Microsoft',
                            width: 120,
                            sortValue: (o) => (o.microsoft ? 1 : 0),
                            cell: (o) =>
                                o.microsoft ? (
                                    <Badge variant="outline">verknüpft</Badge>
                                ) : null,
                        },
                        {
                            id: 'angelegt',
                            header: 'Angelegt',
                            width: 120,
                            sortValue: (o) => o.angelegt ?? '',
                            cell: (o) => o.angelegt ?? '–',
                        },
                    ]}
                />
            </div>
        </>
    );
}
