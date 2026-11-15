import { router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type MailUebersicht = {
    wartend: {
        id: number;
        art: string;
        versuche: number;
        angelegt: string;
        naechster: string;
    }[];
    fehlgeschlagen: { uuid: string; art: string; fehler: string; am: string }[];
    versendet: {
        id: number;
        am: string;
        art: string;
        an: string;
        ergebnis: 'sent' | 'failed';
        weg: string;
        fehler: string | null;
    }[];
};

const zeit = (iso: string) =>
    new Date(iso.replace(' ', 'T')).toLocaleString('de-CH', {
        dateStyle: 'short',
        timeStyle: 'short',
    });

/**
 * Mail-Protokoll (#463): Warteschlange und versendete Mails. Ohne Inhalt und Links, Adressen maskiert; 90 Tage
 * aufbewahrt.
 */
export default function MailLogPanel({ mails }: { mails: MailUebersicht }) {
    return (
        <section className="mt-8">
            <h2 className="text-lg font-semibold">Mails</h2>
            <p className="mt-1 text-sm text-muted-foreground">
                Warteschlange und Protokoll der versendeten Mails – ohne Inhalt
                und Links, Adressen nur gekürzt. Einträge werden nach 90 Tagen
                gelöscht.
            </p>

            <h3 className="mt-4 font-medium">
                Warteschlange ({mails.wartend.length} wartend,{' '}
                {mails.fehlgeschlagen.length} fehlgeschlagen)
            </h3>
            {mails.wartend.length === 0 && mails.fehlgeschlagen.length === 0 ? (
                <p className="mt-1 text-sm text-muted-foreground">
                    Keine Mails in der Warteschlange.
                </p>
            ) : (
                <table className="mt-2 w-full text-sm">
                    <thead className="text-left text-muted-foreground">
                        <tr>
                            <th className="py-1 pr-3 font-medium">Art</th>
                            <th className="py-1 pr-3 font-medium">Stand</th>
                            <th className="py-1 pr-3 font-medium">Zeit</th>
                            <th className="py-1 font-medium">
                                <span className="sr-only">Aktionen</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {mails.wartend.map((m) => (
                            <tr
                                key={`w${m.id}`}
                                className="border-t border-border"
                            >
                                <td className="py-1.5 pr-3">{m.art}</td>
                                <td className="py-1.5 pr-3">
                                    <Badge variant="secondary">wartet</Badge>{' '}
                                    {m.versuche > 0
                                        ? `${m.versuche} Versuche`
                                        : null}
                                </td>
                                <td className="py-1.5 pr-3">
                                    nächster Versuch {zeit(m.naechster)}
                                </td>
                                <td />
                            </tr>
                        ))}
                        {mails.fehlgeschlagen.map((m) => (
                            <tr key={m.uuid} className="border-t border-border">
                                <td className="py-1.5 pr-3">{m.art}</td>
                                <td className="py-1.5 pr-3">
                                    <Badge variant="destructive">
                                        fehlgeschlagen
                                    </Badge>{' '}
                                    <span className="text-muted-foreground">
                                        {m.fehler}
                                    </span>
                                </td>
                                <td className="py-1.5 pr-3">{zeit(m.am)}</td>
                                <td className="py-1.5 text-right whitespace-nowrap">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                `/admin/system/mails/${m.uuid}/wiederholen`,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Erneut versuchen
                                    </Button>{' '}
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            router.delete(
                                                `/admin/system/mails/${m.uuid}`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Verwerfen
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}

            <h3 className="mt-6 font-medium">Zuletzt versendet</h3>
            {mails.versendet.length === 0 ? (
                <p className="mt-1 text-sm text-muted-foreground">
                    Noch keine Mails protokolliert.
                </p>
            ) : (
                <table className="mt-2 w-full text-sm">
                    <thead className="text-left text-muted-foreground">
                        <tr>
                            <th className="py-1 pr-3 font-medium">Zeit</th>
                            <th className="py-1 pr-3 font-medium">Art</th>
                            <th className="py-1 pr-3 font-medium">An</th>
                            <th className="py-1 font-medium">Ergebnis</th>
                        </tr>
                    </thead>
                    <tbody>
                        {mails.versendet.map((m) => (
                            <tr key={m.id} className="border-t border-border">
                                <td className="py-1.5 pr-3 whitespace-nowrap">
                                    {zeit(m.am)}
                                </td>
                                <td className="py-1.5 pr-3">{m.art}</td>
                                <td className="py-1.5 pr-3 break-all">
                                    {m.an}
                                </td>
                                <td className="py-1.5">
                                    {m.ergebnis === 'sent' ? (
                                        <span>
                                            versendet{' '}
                                            <span className="text-muted-foreground">
                                                ({m.weg})
                                            </span>
                                        </span>
                                    ) : (
                                        <Badge variant="destructive">
                                            fehlgeschlagen
                                            {m.fehler ? `: ${m.fehler}` : ''}
                                        </Badge>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </section>
    );
}
