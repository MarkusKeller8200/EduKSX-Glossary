import { router, useForm } from '@inertiajs/react';
import { ArchiveRestore, HardDriveDownload, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { confirmAction } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type Sicherung = {
    name: string;
    groesse: number;
    erstellt: string;
    version: string | null;
    zeilen: number | null;
    verschluesselt: boolean;
    automatisch: boolean;
};

function groesse(bytes: number): string {
    return bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function zeitpunkt(iso: string): string {
    return new Date(iso).toLocaleString('de-CH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

/**
 * Vollständige Sicherung wiederherstellen (#355), nur auf dev: Sicherung auf dem Server anlegen und eine Sicherung
 * vom Server oder eine hochgeladene Datei einspielen. Konten, Rollen und Anmeldungen bleiben unverändert.
 */
export default function RestorePanel({
    sicherungen,
    passwortPflicht,
}: {
    sicherungen: Sicherung[];
    passwortPflicht: boolean;
}) {
    const anlegen = useForm({
        bezeichnung: '',
        passwort: '',
        passwort_confirmation: '',
    });
    const einspielen = useForm<{
        sicherung: string;
        datei: File | null;
        passwort: string;
        bestaetigung: string;
    }>({
        sicherung: sicherungen[0]?.name ?? '',
        datei: null,
        passwort: '',
        bestaetigung: '',
    });
    const [quelle, setQuelle] = useState<'server' | 'datei'>(
        sicherungen.length > 0 ? 'server' : 'datei',
    );

    const sichern = (e: FormEvent) => {
        e.preventDefault();
        anlegen.post('/admin/system/sicherungen', {
            preserveScroll: true,
            onSuccess: () => anlegen.reset(),
        });
    };

    const wiederherstellen = (e: FormEvent) => {
        e.preventDefault();
        einspielen.transform((d) =>
            quelle === 'server'
                ? {
                      sicherung: d.sicherung,
                      passwort: d.passwort,
                      bestaetigung: d.bestaetigung,
                  }
                : {
                      datei: d.datei,
                      passwort: d.passwort,
                      bestaetigung: d.bestaetigung,
                  },
        );
        einspielen.post('/admin/system/wiederherstellen', {
            preserveScroll: true,
            forceFormData: quelle === 'datei',
            onSuccess: () => einspielen.reset('passwort', 'bestaetigung'),
        });
    };

    const loeschen = async (name: string) => {
        if (
            !(await confirmAction(`Sicherung ${name} löschen?`, {
                confirmLabel: 'Löschen',
                destructive: true,
            }))
        ) {
            return;
        }
        router.delete(`/admin/system/sicherungen/${encodeURIComponent(name)}`, {
            preserveScroll: true,
        });
    };

    return (
        <section className="mt-8 max-w-2xl">
            <h2 className="flex items-center gap-2 text-lg font-semibold">
                <ArchiveRestore className="size-5" aria-hidden /> Vollständige
                Sicherung (Restore)
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                Nur auf dev: vor einem Versuch eine Sicherung auf dem Server
                anlegen und sie später wieder einspielen. Konten mit Passwort
                und zweitem Faktor, Anmeldungen über Microsoft, Rollen und die
                laufenden Sitzungen bleiben unverändert; alles andere wird durch
                die Sicherung ersetzt. Vorher wird der aktuelle Stand
                automatisch gesichert.
            </p>

            <form onSubmit={sichern} className="mt-4 grid gap-3 sm:max-w-md">
                <div className="grid gap-2">
                    <Label htmlFor="sicherung-bezeichnung">
                        Bezeichnung (optional)
                    </Label>
                    <Input
                        id="sicherung-bezeichnung"
                        maxLength={40}
                        placeholder="z. B. vor Import"
                        value={anlegen.data.bezeichnung}
                        onChange={(e) =>
                            anlegen.setData('bezeichnung', e.target.value)
                        }
                    />
                    <InputError message={anlegen.errors.bezeichnung} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="sicherung-passwort">
                        Passwort{passwortPflicht ? '' : ' (optional)'}
                    </Label>
                    <Input
                        id="sicherung-passwort"
                        type="password"
                        autoComplete="new-password"
                        minLength={12}
                        required={passwortPflicht}
                        value={anlegen.data.passwort}
                        onChange={(e) =>
                            anlegen.setData('passwort', e.target.value)
                        }
                    />
                </div>
                {anlegen.data.passwort !== '' ? (
                    <div className="grid gap-2">
                        <Label htmlFor="sicherung-wiederholung">
                            Passwort wiederholen
                        </Label>
                        <Input
                            id="sicherung-wiederholung"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={anlegen.data.passwort_confirmation}
                            onChange={(e) =>
                                anlegen.setData(
                                    'passwort_confirmation',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                ) : null}
                <InputError message={anlegen.errors.passwort} />
                <div>
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={anlegen.processing}
                    >
                        <HardDriveDownload aria-hidden />
                        {anlegen.processing
                            ? 'Sicherung wird angelegt …'
                            : 'Sicherung auf dem Server anlegen'}
                    </Button>
                </div>
            </form>

            {sicherungen.length > 0 ? (
                <ul className="mt-6 divide-y rounded-md border text-sm">
                    {sicherungen.map((s) => (
                        <li
                            key={s.name}
                            className="flex items-center justify-between gap-3 px-3 py-2"
                        >
                            <div className="min-w-0">
                                <div className="truncate font-medium">
                                    {s.name}
                                </div>
                                <div className="text-muted-foreground">
                                    {zeitpunkt(s.erstellt)} ·{' '}
                                    {groesse(s.groesse)}
                                    {s.version ? ` · Version ${s.version}` : ''}
                                    {s.zeilen !== null
                                        ? ` · ${s.zeilen} Zeilen`
                                        : ''}
                                    {s.verschluesselt ? ' · verschlüsselt' : ''}
                                    {s.automatisch
                                        ? ' · automatisch vor einer Wiederherstellung'
                                        : ''}
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Sicherung ${s.name} löschen`}
                                onClick={() => void loeschen(s.name)}
                            >
                                <Trash2 aria-hidden />
                            </Button>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="mt-6 text-sm text-muted-foreground">
                    Noch keine Sicherung auf dem Server.
                </p>
            )}

            <form
                onSubmit={wiederherstellen}
                className="mt-6 grid gap-3 sm:max-w-md"
            >
                <fieldset className="grid gap-2">
                    <legend className="mb-1 text-sm font-medium">
                        Wiederherstellen aus
                    </legend>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="radio"
                            name="quelle"
                            checked={quelle === 'server'}
                            disabled={sicherungen.length === 0}
                            onChange={() => setQuelle('server')}
                        />
                        Sicherung auf dem Server
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="radio"
                            name="quelle"
                            checked={quelle === 'datei'}
                            onChange={() => setQuelle('datei')}
                        />
                        Datei hochladen (Dump, höchstens 10 MB)
                    </label>
                </fieldset>
                {quelle === 'server' ? (
                    <div className="grid gap-2">
                        <Label htmlFor="restore-sicherung">Sicherung</Label>
                        <select
                            id="restore-sicherung"
                            className="h-9 rounded-md border bg-transparent px-3 text-sm"
                            value={einspielen.data.sicherung}
                            onChange={(e) =>
                                einspielen.setData('sicherung', e.target.value)
                            }
                        >
                            {sicherungen.map((s) => (
                                <option key={s.name} value={s.name}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={einspielen.errors.sicherung} />
                    </div>
                ) : (
                    <div className="grid gap-2">
                        <Label htmlFor="restore-datei">ZIP-Datei</Label>
                        <Input
                            id="restore-datei"
                            type="file"
                            accept=".zip,application/zip"
                            required
                            onChange={(e) =>
                                einspielen.setData(
                                    'datei',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={einspielen.errors.datei} />
                    </div>
                )}
                <div className="grid gap-2">
                    <Label htmlFor="restore-passwort">
                        Passwort der Sicherung (falls verschlüsselt)
                    </Label>
                    <Input
                        id="restore-passwort"
                        type="password"
                        autoComplete="off"
                        value={einspielen.data.passwort}
                        onChange={(e) =>
                            einspielen.setData('passwort', e.target.value)
                        }
                    />
                    <InputError message={einspielen.errors.passwort} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="restore-bestaetigung">
                        Zur Bestätigung «WIEDERHERSTELLEN» eingeben
                    </Label>
                    <Input
                        id="restore-bestaetigung"
                        autoComplete="off"
                        required
                        value={einspielen.data.bestaetigung}
                        onChange={(e) =>
                            einspielen.setData('bestaetigung', e.target.value)
                        }
                    />
                    <InputError message={einspielen.errors.bestaetigung} />
                </div>
                <div>
                    <Button
                        type="submit"
                        disabled={
                            einspielen.processing ||
                            einspielen.data.bestaetigung !== 'WIEDERHERSTELLEN'
                        }
                    >
                        <ArchiveRestore aria-hidden />
                        {einspielen.processing
                            ? 'Wird wiederhergestellt …'
                            : 'Sicherung wiederherstellen'}
                    </Button>
                </div>
            </form>
        </section>
    );
}
