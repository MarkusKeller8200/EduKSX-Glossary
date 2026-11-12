import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/** Excel-Import (#13, #15): Datei wählen → Vorschau. Format = Export des Intranets. */
type Protokoll = {
    id: number;
    am: string;
    von: string | null;
    datei: string;
    format: number;
    zeilen: number;
    neu: number;
    geaendert: number;
    fehler: number;
    meldungen: { zeile: number; meldung: string }[];
};

export default function Import({
    spalten,
    protokoll,
}: {
    spalten: string[];
    protokoll: Protokoll[];
}) {
    const form = useForm<{ datei: File | null; listen_anlegen: boolean }>({
        datei: null,
        listen_anlegen: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/admin/import', { forceFormData: true });
    };

    return (
        <>
            <Head title="Excel-Import" />
            <h1 className="text-3xl font-semibold tracking-tight">
                Excel-Import
            </h1>
            <p className="mt-2 text-sm text-muted-foreground">
                Export des Intranets (Glossar → Excel-Export) oder eine
                ausgefüllte Vorlage hochladen. Zuerst erscheint eine Vorschau;
                gespeichert wird erst mit «Übernehmen». Zeilen ohne ID werden
                neu angelegt, Zeilen mit ID aktualisiert – eine ID aus dem
                Intranet bleibt erhalten. Es wird nie etwas gelöscht. Neue
                Begriffe sind Entwürfe.
            </p>

            <Card className="mt-6 max-w-2xl">
                <CardHeader>
                    <CardTitle>Datei</CardTitle>
                    <CardDescription>
                        Excel (.xlsx) oder CSV, höchstens 5 MB.{' '}
                        <a
                            href="/admin/export?vorlage=1"
                            className="text-link underline underline-offset-4"
                        >
                            Leere Vorlage herunterladen
                        </a>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="datei">Datei wählen</Label>
                            <input
                                id="datei"
                                type="file"
                                accept=".xlsx,.csv"
                                required
                                className="block w-full text-sm file:mr-3 file:rounded-md file:border file:border-input file:bg-background file:px-3 file:py-1.5 file:text-sm"
                                onChange={(e) =>
                                    form.setData(
                                        'datei',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={form.errors.datei} />
                        </div>
                        <div className="flex items-start gap-2">
                            <Checkbox
                                id="listen_anlegen"
                                checked={form.data.listen_anlegen}
                                onCheckedChange={(v) =>
                                    form.setData('listen_anlegen', v === true)
                                }
                            />
                            <Label
                                htmlFor="listen_anlegen"
                                className="leading-snug font-normal"
                            >
                                Unbekannte Kategorien und Herkünfte neu anlegen
                                (sonst sind diese Zeilen Fehler)
                            </Label>
                        </div>
                        <Button
                            type="submit"
                            disabled={form.processing || !form.data.datei}
                        >
                            Vorschau anzeigen
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <details className="mt-6 max-w-2xl text-sm">
                <summary className="cursor-pointer text-muted-foreground">
                    Spalten ({spalten.length})
                </summary>
                <p className="mt-2" translate="no">
                    {spalten.join(' · ')}
                </p>
                <p className="mt-2 text-muted-foreground">
                    Mehrfachwerte (Kategorien, Aliase offiziell, Alternative
                    Schreibweisen, Herkunft) mit «;» trennen. Aktiv: ja/nein.
                    Status: aktuell, historisch oder zu prüfen.
                </p>
            </details>

            <section className="mt-10">
                <h2 className="text-lg font-semibold">Letzte Importe</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Protokoll der übernommenen Dateien (12 Monate aufbewahrt).
                    Die einzelnen Änderungen stehen im Verlauf der Begriffe.
                </p>
                {protokoll.length === 0 ? (
                    <p className="mt-3 text-sm text-muted-foreground">
                        Noch kein Import übernommen.
                    </p>
                ) : (
                    <ul className="mt-3 divide-y divide-border border-y border-border text-sm">
                        {protokoll.map((p) => (
                            <li key={p.id} className="py-2">
                                <span className="font-medium">{p.datei}</span>{' '}
                                <span className="text-muted-foreground">
                                    · {new Date(p.am).toLocaleString('de-CH')}
                                    {p.von ? ` · ${p.von}` : ''} · Format{' '}
                                    {p.format}
                                </span>
                                <span className="block">
                                    {p.zeilen} Zeilen: {p.neu} neu,{' '}
                                    {p.geaendert} geändert, {p.fehler} Fehler
                                </span>
                                {p.meldungen.length > 0 ? (
                                    <details className="mt-1">
                                        <summary className="cursor-pointer text-muted-foreground">
                                            Fehler anzeigen
                                        </summary>
                                        <ul className="mt-1 list-disc pl-5">
                                            {p.meldungen.map((m) => (
                                                <li key={`${p.id}-${m.zeile}`}>
                                                    Zeile {m.zeile}: {m.meldung}
                                                </li>
                                            ))}
                                        </ul>
                                    </details>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </>
    );
}
