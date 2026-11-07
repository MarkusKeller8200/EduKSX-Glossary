import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { NativeSelect } from '@/components/ui/native-select';

type Wert = {
    id: number;
    term_id: string;
    titel: string;
    zusammengefuehrt: boolean;
    offiziell: string;
    feld: string;
    feld_label: string;
    wert: string;
    herkunft: string;
    zuordnung: string | null;
    erlaubt: string[];
};

/**
 * Altwerte (DEC-020): eigene Abkürzungen, Bezeichnungen, Aliase und «Intern genutzt in» aus dem alten
 * Intranet-Format. Nichts geschieht automatisch – je Wert übernehmen oder verwerfen.
 */
export default function LegacyValues({
    werte,
    alle,
    offen,
    zuordnungen,
}: {
    werte: Wert[];
    alle: boolean;
    offen: number;
    zuordnungen: Record<string, string>;
}) {
    const [auswahl, setAuswahl] = useState<number[]>([]);
    const [zuordnung, setZuordnung] = useState('alternative');
    const [feld, setFeld] = useState('');
    const [sending, setSending] = useState(false);

    const sichtbar = useMemo(
        () => werte.filter((w) => feld === '' || w.feld === feld),
        [werte, feld],
    );
    const waehlbar = sichtbar.filter((w) => w.zuordnung === null);
    const felder = Array.from(
        new Map(werte.map((w) => [w.feld, w.feld_label])).entries(),
    );
    const toggle = (id: number, on: boolean) =>
        setAuswahl((a) => (on ? [...a, id] : a.filter((x) => x !== id)));
    const zuordnen = () => {
        setSending(true);
        router.post(
            '/admin/altwerte',
            { ids: auswahl, zuordnung },
            {
                preserveScroll: true,
                onSuccess: () => setAuswahl([]),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <>
            <Head title="Altwerte" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Altwerte zuordnen
                    </h1>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <a href="/admin/altwerte/intern-genutzt-in.csv">
                            «Intern genutzt in» als CSV
                        </a>
                    </Button>
                    <Button asChild variant="outline">
                        <Link
                            href="/admin/altwerte"
                            data={alle ? {} : { anzeige: 'alle' }}
                        >
                            {alle ? 'Nur offene' : 'Auch zugeordnete'}
                        </Link>
                    </Button>
                </div>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">
                {offen === 1 ? '1 offener Wert' : `${offen} offene Werte`} aus
                dem Intranet-Format. Eigene Bezeichnungen und «Intern genutzt
                in» sind keine gemeinsamen Daten mehr. Übernehmen als offizielle
                Angabe (nur wenn das Feld leer ist), als alternative
                Schreibweise oder als Suchbegriff – oder verwerfen. Übernahmen
                ergeben einen Entwurf, der wie jede Änderung zu veröffentlichen
                ist. Persönliche Formen setzt jede Person selbst unter «Meine
                Darstellung».
            </p>

            <div className="mt-6 flex flex-wrap items-end gap-3 text-sm">
                <label className="grid gap-1">
                    <span className="text-muted-foreground">Feld</span>
                    <NativeSelect
                        value={feld}
                        onChange={(e) => setFeld(e.target.value)}
                    >
                        <option value="">alle</option>
                        {felder.map(([k, l]) => (
                            <option key={k} value={k}>
                                {l}
                            </option>
                        ))}
                    </NativeSelect>
                </label>
                <label className="grid gap-1">
                    <span className="text-muted-foreground">Zuordnung</span>
                    <NativeSelect
                        value={zuordnung}
                        onChange={(e) => setZuordnung(e.target.value)}
                    >
                        {Object.entries(zuordnungen).map(([k, l]) => (
                            <option key={k} value={k}>
                                {l}
                            </option>
                        ))}
                    </NativeSelect>
                </label>
                <Button
                    disabled={auswahl.length === 0 || sending}
                    onClick={() => {
                        if (
                            zuordnung !== 'discarded' ||
                            window.confirm(
                                `${auswahl.length} Altwerte verwerfen? Sie bleiben gesichert, werden aber nicht mehr angeboten.`,
                            )
                        ) {
                            zuordnen();
                        }
                    }}
                >
                    {auswahl.length} ausgewählte zuordnen
                </Button>
            </div>

            <div className="mt-4 overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted text-left">
                        <tr>
                            <th scope="col" className="w-10 px-3 py-2">
                                <Checkbox
                                    aria-label="Alle sichtbaren wählen"
                                    checked={
                                        waehlbar.length > 0 &&
                                        waehlbar.every((w) =>
                                            auswahl.includes(w.id),
                                        )
                                    }
                                    onCheckedChange={(c) =>
                                        setAuswahl(
                                            c === true
                                                ? waehlbar.map((w) => w.id)
                                                : [],
                                        )
                                    }
                                />
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Begriff
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Feld
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Wert
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Herkunft
                            </th>
                            <th scope="col" className="px-3 py-2 font-medium">
                                Zuordnung
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {sichtbar.map((w) => (
                            <tr
                                key={w.id}
                                className="border-t border-border align-top"
                            >
                                <td className="px-3 py-2">
                                    {w.zuordnung === null ? (
                                        <Checkbox
                                            aria-label={`${w.wert} wählen`}
                                            checked={auswahl.includes(w.id)}
                                            onCheckedChange={(c) =>
                                                toggle(w.id, c === true)
                                            }
                                        />
                                    ) : null}
                                </td>
                                <td className="px-3 py-2" translate="no">
                                    <Link
                                        href={`/admin/begriffe/${w.term_id}`}
                                        className="text-link underline-offset-4 hover:underline"
                                    >
                                        {w.titel || '(ohne Bezeichnung)'}
                                    </Link>
                                    {w.zusammengefuehrt ? (
                                        <Badge
                                            variant="outline"
                                            className="ml-2"
                                        >
                                            zusammengeführt
                                        </Badge>
                                    ) : null}
                                </td>
                                <td className="px-3 py-2">{w.feld_label}</td>
                                <td className="px-3 py-2" translate="no">
                                    {w.wert}
                                    {w.offiziell !== '' &&
                                    w.offiziell !== w.wert ? (
                                        <span className="block text-xs text-muted-foreground">
                                            offiziell: {w.offiziell}
                                        </span>
                                    ) : null}
                                    {w.erlaubt.length === 1 ? (
                                        <span className="block text-xs text-muted-foreground">
                                            nur verwerfen (im Intranet
                                            verwalten)
                                        </span>
                                    ) : null}
                                </td>
                                <td className="px-3 py-2">{w.herkunft}</td>
                                <td className="px-3 py-2">
                                    {w.zuordnung ?? (
                                        <Badge variant="secondary">offen</Badge>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {sichtbar.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="px-3 py-6 text-center text-muted-foreground"
                                >
                                    Keine Altwerte – alles ist zugeordnet.
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>
        </>
    );
}
