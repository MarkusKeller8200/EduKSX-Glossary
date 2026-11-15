export type VerlaufEintrag = {
    am: string;
    wer: string;
    was: string;
    details: string;
};

function datum(utc: string): string {
    const d = new Date(utc.replace(' ', 'T') + (utc.endsWith('Z') ? '' : 'Z'));

    return Number.isNaN(d.getTime())
        ? utc
        : d.toLocaleString('de-CH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          });
}

/**
 * Verlauf eines Kontos bzw. einer Organisation (#417): eigener Bereich ganz unten, aufklappbar. Wer, wann, was – auch
 * Änderungen durch Admin und Organisations-Admin; personenbezogene Werte nur als «geändert».
 */
export default function HistoryPanel({
    eintraege,
}: {
    eintraege: VerlaufEintrag[];
}) {
    return (
        <details className="group mt-10 rounded-lg border border-border">
            <summary className="cursor-pointer px-4 py-3 text-lg font-semibold">
                Verlauf{' '}
                <span className="text-sm font-normal text-muted-foreground">
                    ({eintraege.length})
                </span>
            </summary>
            <div className="border-t border-border px-4 py-3">
                {eintraege.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Noch keine Einträge.
                    </p>
                ) : (
                    <ol className="grid gap-2 text-sm">
                        {eintraege.map((e, i) => (
                            <li
                                key={`${e.am}-${i}`}
                                className="grid gap-x-4 sm:grid-cols-[11rem_1fr]"
                            >
                                <span className="text-muted-foreground tabular-nums">
                                    {datum(e.am)}
                                </span>
                                <span>
                                    <span className="font-medium">{e.was}</span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {e.wer}
                                    </span>
                                    {e.details ? (
                                        <span className="block text-xs text-muted-foreground">
                                            {e.details}
                                        </span>
                                    ) : null}
                                </span>
                            </li>
                        ))}
                    </ol>
                )}
            </div>
        </details>
    );
}
