import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { confirmAction } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type UpdateStand = {
    aktiv: boolean;
    installiert: string;
    neueste: string | null;
    neu: boolean;
    art: 'korrektur' | 'funktion' | 'hauptversion' | null;
    url: string | null;
    dienst: boolean;
    stand: { status?: string; meldung?: string; gemeldet?: string } | null;
    auftrag: { ziel?: string; angefordert_von?: string; am?: string } | null;
    protokoll: {
        ergebnis: string;
        von: string;
        nach: string;
        start: string;
        ende: string;
        meldung: string;
        angefordert_von: string;
    }[];
    schluessel: boolean;
};

const ART: Record<string, string> = {
    korrektur: 'Korrektur',
    funktion: 'neue Funktionen',
    hauptversion: 'Hauptversion',
};

const datum = (iso: string) =>
    iso ? new Date(iso).toLocaleString('de-CH') : '';

/** Updates (#176, #177): installierte und neueste Version, Auftrag an den Update-Dienst, Protokoll. */
export default function UpdatePanel({ update: u }: { update: UpdateStand }) {
    const form = useForm({ ziel: u.neueste ?? '', schluessel: '' });
    const submit = async (e: FormEvent) => {
        e.preventDefault();
        const ok = await confirmAction(
            `Auf ${u.neueste ?? ''} aktualisieren? Vor dem Neustart wird die Datenbank gesichert; die Website ist kurz nicht erreichbar. Schlägt die Prüfung fehl, springt der Dienst automatisch auf den bisherigen Stand zurück.`,
            { confirmLabel: 'Aktualisieren' },
        );
        if (ok) {
            form.post('/admin/system/update', { preserveScroll: true });
        }
    };

    return (
        <section className="mt-8 max-w-3xl">
            <h2 className="flex items-center gap-2 text-lg font-semibold">
                Updates
                {u.neu ? (
                    <Badge>
                        Neue Version {u.neueste}
                        {u.art ? ` (${ART[u.art]})` : ''}
                    </Badge>
                ) : null}
            </h2>
            <dl className="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[16rem_1fr]">
                <dt className="text-muted-foreground">Installiert</dt>
                <dd>{u.installiert}</dd>
                <dt className="text-muted-foreground">Neueste Version</dt>
                <dd>
                    {!u.aktiv
                        ? 'Prüfung ausgeschaltet (Einstellungen)'
                        : (u.neueste ?? 'unbekannt (nicht erreichbar)')}
                    {u.url ? (
                        <>
                            {' '}
                            –{' '}
                            <a
                                href={u.url}
                                className="text-link underline underline-offset-4"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Änderungen
                            </a>
                        </>
                    ) : null}
                </dd>
                <dt className="text-muted-foreground">Update-Dienst</dt>
                <dd>
                    {u.dienst
                        ? `aktiv – ${u.stand?.meldung ?? ''}`
                        : 'nicht eingerichtet (Anleitung «Selbst hosten»)'}
                </dd>
                {u.auftrag ? (
                    <>
                        <dt className="text-muted-foreground">
                            Offener Auftrag
                        </dt>
                        <dd>
                            {u.auftrag.ziel} – {u.auftrag.angefordert_von},{' '}
                            {datum(u.auftrag.am ?? '')}
                        </dd>
                    </>
                ) : null}
            </dl>

            {u.dienst && u.neu && !u.auftrag ? (
                <form onSubmit={submit} className="mt-4 grid max-w-md gap-3">
                    {u.schluessel ? (
                        <div className="grid gap-2">
                            <Label htmlFor="schluessel">Update-Schlüssel</Label>
                            <Input
                                id="schluessel"
                                type="password"
                                autoComplete="off"
                                value={form.data.schluessel}
                                onChange={(e) =>
                                    form.setData('schluessel', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.schluessel} />
                        </div>
                    ) : null}
                    <InputError message={form.errors.ziel} />
                    <div>
                        <Button type="submit" disabled={form.processing}>
                            Jetzt auf {u.neueste} aktualisieren
                        </Button>
                    </div>
                </form>
            ) : null}

            {u.protokoll.length > 0 ? (
                <table className="mt-4 w-full text-sm">
                    <thead className="text-left text-muted-foreground">
                        <tr>
                            <th className="py-1 pr-3 font-medium">Ende</th>
                            <th className="py-1 pr-3 font-medium">Ergebnis</th>
                            <th className="py-1 pr-3 font-medium">Nach</th>
                            <th className="py-1 font-medium">Von wem</th>
                        </tr>
                    </thead>
                    <tbody>
                        {u.protokoll.map((p) => (
                            <tr
                                key={p.start + p.nach}
                                className="border-t border-border align-top"
                                title={p.meldung}
                            >
                                <td className="py-1.5 pr-3">{datum(p.ende)}</td>
                                <td className="py-1.5 pr-3">{p.ergebnis}</td>
                                <td className="py-1.5 pr-3">
                                    {p.nach.split(':').pop()}
                                </td>
                                <td className="py-1.5">{p.angefordert_von}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : null}
        </section>
    );
}
