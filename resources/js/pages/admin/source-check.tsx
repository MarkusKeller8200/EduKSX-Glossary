import { Head, Link } from '@inertiajs/react';

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
            <div className="mt-6 overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted text-left">
                        <tr>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Begriff
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Gründe
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {begriffe.map((b) => (
                            <tr
                                key={b.id}
                                className="border-t border-border align-top"
                            >
                                <td className="px-3 py-2" translate="no">
                                    <Link
                                        href={`/admin/begriffe/${b.id}#herkunft`}
                                        className="text-link underline-offset-4 hover:underline"
                                    >
                                        {b.titel || '(ohne Bezeichnung)'}
                                    </Link>
                                </td>
                                <td className="px-3 py-2">
                                    <ul className="list-disc pl-4">
                                        {b.gruende.map((g) => (
                                            <li key={g}>{g}</li>
                                        ))}
                                    </ul>
                                </td>
                            </tr>
                        ))}
                        {begriffe.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={2}
                                    className="px-3 py-6 text-center text-muted-foreground"
                                >
                                    Alles geprüft.
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>
        </>
    );
}
