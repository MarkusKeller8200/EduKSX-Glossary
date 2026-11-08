import { Link } from '@inertiajs/react';
import { ChevronDown, Download } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type Format = 'xlsx' | 'csv' | 'md';
type Umfang = 'sichtbar' | 'vollstaendig';

/** XSRF-Token aus dem Cookie (Laravel prüft den Header X-XSRF-TOKEN). */
function xsrf(): string {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return m ? decodeURIComponent(m[1]) : '';
}

/**
 * Export-Menü (#131, wie im Intranet): sichtbare Zeilen als Excel (sichtbare Spalten), CSV, Excel vollständig,
 * PDF (Dialog) und Markdown. Der Server prüft das Recht; ohne Recht ist der Knopf ausgegraut mit Hinweis.
 */
export default function ExportMenu({
    endpoint,
    keys,
    allowed,
    hint,
    onPdf,
}: {
    /** `/export` (veröffentlicht) bzw. `/admin/export` (Arbeitsstand) */
    endpoint: string;
    /** IDs oder Slugs der sichtbaren Zeilen in angezeigter Reihenfolge; ohne Angabe alle */
    keys?: string[];
    allowed: boolean;
    /** Hinweis ohne Recht, z. B. «Zum Exportieren anmelden» */
    hint?: { text: string; href?: string; link?: string };
    onPdf?: () => void;
}) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const n = keys?.length ?? null;

    const download = async (format: Format, umfang: Umfang) => {
        setBusy(true);
        setError(null);
        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/octet-stream, application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrf(),
                },
                body: JSON.stringify(
                    keys ? { format, umfang, keys } : { format, umfang },
                ),
            });
            if (!res.ok) {
                throw new Error(String(res.status));
            }
            const name =
                /filename="([^"]+)"/.exec(
                    res.headers.get('Content-Disposition') ?? '',
                )?.[1] ?? `glossary.${format}`;
            const url = URL.createObjectURL(await res.blob());
            const a = document.createElement('a');
            a.href = url;
            a.download = name;
            document.body.append(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch {
            setError(
                'Der Export ist fehlgeschlagen. Bitte neu laden und nochmals versuchen.',
            );
        } finally {
            setBusy(false);
        }
    };

    if (!allowed) {
        return (
            <span className="inline-flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    disabled
                    aria-describedby="export-hinweis"
                    title={hint?.text}
                >
                    <Download aria-hidden /> Export
                    <ChevronDown aria-hidden />
                </Button>
                {hint ? (
                    <span
                        id="export-hinweis"
                        className="text-sm text-muted-foreground"
                    >
                        {hint.text}
                        {hint.href ? (
                            <>
                                {' – '}
                                <Link
                                    href={hint.href}
                                    className="text-link underline underline-offset-4"
                                >
                                    {hint.link ?? 'Anmelden'}
                                </Link>
                            </>
                        ) : null}
                    </span>
                ) : null}
            </span>
        );
    }

    const zeilen = n === null ? 'alle Begriffe' : `sichtbare Zeilen (${n})`;

    return (
        <span className="inline-flex flex-wrap items-center gap-2">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline" disabled={busy}>
                        <Download aria-hidden />
                        {busy ? 'Export läuft …' : 'Export'}
                        <ChevronDown aria-hidden />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuLabel>
                        Sichtbare Spalten in dieser Reihenfolge
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        disabled={n === 0}
                        onSelect={() => void download('xlsx', 'sichtbar')}
                    >
                        Excel – {zeilen}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        disabled={n === 0}
                        onSelect={() => void download('csv', 'sichtbar')}
                    >
                        CSV – {zeilen}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        disabled={n === 0}
                        onSelect={() => void download('xlsx', 'vollstaendig')}
                    >
                        Excel vollständig (alle Daten) – {zeilen}
                    </DropdownMenuItem>
                    {onPdf ? (
                        <DropdownMenuItem
                            disabled={n === 0}
                            onSelect={() => onPdf()}
                        >
                            PDF … – {zeilen}
                        </DropdownMenuItem>
                    ) : null}
                    <DropdownMenuItem
                        disabled={n === 0}
                        onSelect={() => void download('md', 'sichtbar')}
                    >
                        Markdown – {zeilen}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
            {error ? (
                <span className="text-sm text-destructive" role="alert">
                    {error}
                </span>
            ) : null}
        </span>
    );
}
