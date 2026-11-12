import { Database, Download, LockKeyhole } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** XSRF-Token aus dem Cookie (Laravel prüft den Header X-XSRF-TOKEN). */
function xsrf(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return m ? decodeURIComponent(m[1]) : '';
}

/**
 * Vollständiger Dump der Datenbank zum Herunterladen (#268): ZIP mit SQLite-Kopie, allen Tabellen als JSON und
 * Manifest; mit Passwort AES-256-verschlüsselt. Ohne Passwort nur, solange es nicht vorgeschrieben ist.
 */
export default function DumpPanel({
    passwortPflicht,
}: {
    passwortPflicht: boolean;
}) {
    const [passwort, setPasswort] = useState('');
    const [wiederholung, setWiederholung] = useState('');
    const [fehler, setFehler] = useState<string | null>(null);
    const [laeuft, setLaeuft] = useState(false);

    const herunterladen = async (e: FormEvent) => {
        e.preventDefault();
        setFehler(null);
        setLaeuft(true);
        try {
            const res = await fetch('/admin/system/dump', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/zip, application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrf(),
                },
                body: JSON.stringify(
                    passwort === ''
                        ? {}
                        : {
                              passwort,
                              passwort_confirmation: wiederholung,
                          },
                ),
            });
            if (!res.ok) {
                const data = (await res.json().catch(() => null)) as {
                    errors?: Record<string, string[]>;
                    message?: string;
                } | null;
                setFehler(
                    data?.errors?.passwort?.[0] ??
                        (res.status === 429
                            ? 'Zu viele Versuche – bitte in einigen Minuten nochmals.'
                            : 'Der Dump ist fehlgeschlagen.'),
                );

                return;
            }
            const name =
                /filename="?([^";]+)"?/.exec(
                    res.headers.get('Content-Disposition') ?? '',
                )?.[1] ?? 'glossary-dump.zip';
            const url = URL.createObjectURL(await res.blob());
            const a = document.createElement('a');
            a.href = url;
            a.download = name;
            document.body.append(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            setPasswort('');
            setWiederholung('');
        } catch {
            setFehler('Der Dump ist fehlgeschlagen.');
        } finally {
            setLaeuft(false);
        }
    };

    return (
        <section className="mt-8 max-w-2xl">
            <h2 className="flex items-center gap-2 text-lg font-semibold">
                <Database className="size-5" aria-hidden /> Vollständige
                Sicherung (Dump)
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                ZIP mit einer exakten Kopie der Datenbank, allen Tabellen als
                JSON und einem Manifest (Zeilen je Tabelle, Prüfsummen). Der
                Dump enthält alle Daten, auch Personendaten, Passwort-Hashes und
                Schlüssel – nur sicher aufbewahren.
                {passwortPflicht
                    ? ' Ein Passwort ist vorgeschrieben.'
                    : ' Mit Passwort wird er mit AES-256 verschlüsselt (öffnet z. B. 7-Zip); ohne Passwort nur vorübergehend erlaubt.'}
            </p>
            <form
                onSubmit={(e) => void herunterladen(e)}
                className="mt-4 grid gap-3 sm:max-w-md"
            >
                <div className="grid gap-2">
                    <Label htmlFor="dump-passwort">
                        Passwort{passwortPflicht ? '' : ' (optional)'}
                    </Label>
                    <Input
                        id="dump-passwort"
                        type="password"
                        autoComplete="new-password"
                        minLength={12}
                        required={passwortPflicht}
                        value={passwort}
                        onChange={(e) => setPasswort(e.target.value)}
                    />
                </div>
                {passwort !== '' ? (
                    <div className="grid gap-2">
                        <Label htmlFor="dump-wiederholung">
                            Passwort wiederholen
                        </Label>
                        <Input
                            id="dump-wiederholung"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={wiederholung}
                            onChange={(e) => setWiederholung(e.target.value)}
                        />
                    </div>
                ) : null}
                <InputError message={fehler ?? undefined} />
                <div>
                    <Button type="submit" disabled={laeuft}>
                        {passwort !== '' ? (
                            <LockKeyhole aria-hidden />
                        ) : (
                            <Download aria-hidden />
                        )}
                        {laeuft
                            ? 'Dump wird erstellt …'
                            : passwort !== ''
                              ? 'Verschlüsselten Dump herunterladen'
                              : 'Dump ohne Passwort herunterladen'}
                    </Button>
                </div>
            </form>
        </section>
    );
}
