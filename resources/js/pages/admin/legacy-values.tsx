import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { NativeSelect } from '@/components/ui/native-select';
import DataGrid from '@/components/data-grid';
import { confirmAction } from '@/components/confirm-dialog';

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
                    onClick={async () => {
                        if (
                            zuordnung !== 'discarded' ||
                            (await confirmAction(
                                `${auswahl.length} Altwerte verwerfen? Sie bleiben gesichert, werden aber nicht mehr angeboten.`,
                            ))
                        ) {
                            zuordnen();
                        }
                    }}
                >
                    {auswahl.length} ausgewählte zuordnen
                </Button>
            </div>

            <div className="mt-4">
                <DataGrid
                    id="admin.legacy-values"
                    caption="Altwerte"
                    rows={sichtbar}
                    getRowId={(w: Wert) => String(w.id)}
                    empty="Keine Altwerte – alles ist zugeordnet."
                    columns={[
                        {
                            id: 'auswahl',
                            label: 'Auswahl',
                            header: (
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
                            ),
                            width: 44,
                            fixed: true,
                            sticky: 'left',
                            cell: (w) =>
                                w.zuordnung === null ? (
                                    <Checkbox
                                        aria-label={`${w.wert} wählen`}
                                        checked={auswahl.includes(w.id)}
                                        onCheckedChange={(c) =>
                                            toggle(w.id, c === true)
                                        }
                                    />
                                ) : null,
                        },
                        {
                            id: 'begriff',
                            header: 'Begriff',
                            width: 220,
                            sticky: 'left',
                            noTranslate: true,
                            sortValue: (w) => w.titel,
                            cell: (w) => (
                                <>
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
                                </>
                            ),
                        },
                        {
                            id: 'feld',
                            header: 'Feld',
                            width: 150,
                            sortValue: (w) => w.feld_label,
                            cell: (w) => w.feld_label,
                        },
                        {
                            id: 'wert',
                            header: 'Wert',
                            width: 240,
                            noTranslate: true,
                            sortValue: (w) => w.wert,
                            cell: (w) => (
                                <>
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
                                </>
                            ),
                        },
                        {
                            id: 'herkunft',
                            header: 'Herkunft',
                            width: 160,
                            sortValue: (w) => w.herkunft,
                            cell: (w) => w.herkunft,
                        },
                        {
                            id: 'zuordnung',
                            header: 'Zuordnung',
                            width: 160,
                            sortValue: (w) => w.zuordnung ?? '',
                            cell: (w) =>
                                w.zuordnung ?? (
                                    <Badge variant="secondary">offen</Badge>
                                ),
                        },
                    ]}
                />
            </div>
        </>
    );
}
