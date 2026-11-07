import { usePage } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    PDF_DEFAULT,
    printGlossary,
    type PdfOptions,
    type PdfScope,
    type PrintRow,
} from '@/lib/print-glossary';

const STORAGE_KEY = 'glossary.pdf';

type Auswahl = 'alle' | 'gefiltert' | 'ausgewaehlt';

const CHOICES: {
    [K in keyof PdfOptions]: {
        label: string;
        options: Record<PdfOptions[K], string>;
    };
} = {
    format: { label: 'Format', options: { hoch: 'A4 hoch', quer: 'A4 quer' } },
    umfang: {
        label: 'Umfang',
        options: {
            kompakt: 'Kompakt',
            erweitert: 'Erweitert',
            vollstaendig: 'Vollständig',
        },
    },
    gruppe: {
        label: 'Gruppiert',
        options: {
            keine: 'Keine',
            kategorie: 'Kategorie',
            herkunft: 'Herkunft',
            status: 'Status',
        },
    },
    umbruch: {
        label: 'Seitenumbruch',
        options: { keiner: 'Keiner', gruppe: 'Bei Gruppe' },
    },
};

const HINT: Record<PdfScope, string> = {
    kompakt: 'Eine Zeile je Eintrag, zu lange Texte mit «…».',
    erweitert: 'Mit Aliasen und Beschreibung – nichts abgeschnitten.',
    vollstaendig:
        'Alle Felder inkl. Status und Gültigkeit (in der Redaktion mit Notiz).',
};

function load(): PdfOptions {
    try {
        const saved = JSON.parse(
            localStorage.getItem(STORAGE_KEY) ?? '{}',
        ) as Partial<PdfOptions>;
        const valid = (Object.keys(CHOICES) as (keyof PdfOptions)[]).every(
            (k) => saved[k] === undefined || saved[k] in CHOICES[k].options,
        );

        return valid ? { ...PDF_DEFAULT, ...saved } : PDF_DEFAULT;
    } catch {
        return PDF_DEFAULT;
    }
}

function Wahl<T extends string>({
    id,
    label,
    value,
    options,
    disabled,
    onChange,
}: {
    id: string;
    label: string;
    value: T;
    options: Partial<Record<T, string>>;
    disabled?: (value: T) => boolean;
    onChange: (value: T) => void;
}) {
    return (
        <div className="grid gap-1.5 sm:grid-cols-[8rem_1fr] sm:items-center">
            <span id={id} className="text-sm font-medium">
                {label}
            </span>
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                className="flex-wrap justify-start"
                aria-labelledby={id}
                value={value}
                onValueChange={(v: string) => v && onChange(v as T)}
            >
                {(Object.entries(options) as [T, string][]).map(
                    ([key, text]) => (
                        <ToggleGroupItem
                            key={key}
                            value={key}
                            className="px-3"
                            disabled={disabled?.(key)}
                        >
                            {text}
                        </ToggleGroupItem>
                    ),
                )}
            </ToggleGroup>
        </div>
    );
}

/**
 * PDF-Export (#112, aus dem Intranet `glossary-pdf-dialog.tsx`): Format, Umfang, Gruppierung, Seitenumbruch und
 * Auswahl der Begriffe (alle, gefiltert, ausgewählt). Die Zeilen holt der Dialog erst beim Erstellen von `quelle`
 * (veröffentlicht: `/export?format=json`, Redaktion: `/admin/export?format=json`). Die letzte Wahl bleibt im Browser.
 */
export default function GlossaryPdfDialog({
    open,
    onOpenChange,
    quelle,
    titel = 'Glossary',
    gefiltert,
    ausgewaehlt,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    quelle: string;
    titel?: string;
    /** IDs oder Slugs der aktuell gefilterten Begriffe; ohne Angabe keine Wahl «gefiltert» */
    gefiltert?: string[];
    /** IDs der ausgewählten Begriffe; ohne Angabe keine Wahl «ausgewählt» */
    ausgewaehlt?: string[];
}) {
    const { farben } = usePage().props;
    const [o, setO] = useState<PdfOptions>(load);
    const [auswahl, setAuswahl] = useState<Auswahl>(
        ausgewaehlt?.length ? 'ausgewaehlt' : gefiltert ? 'gefiltert' : 'alle',
    );
    const [laden, setLaden] = useState(false);
    const [fehler, setFehler] = useState<string | null>(null);
    const set = <K extends keyof PdfOptions>(key: K, value: PdfOptions[K]) =>
        setO((current) => ({ ...current, [key]: value }));

    const auswahlOptionen: Partial<Record<Auswahl, string>> = {
        alle: 'Alle',
        ...(gefiltert ? { gefiltert: `Gefiltert (${gefiltert.length})` } : {}),
        ...(ausgewaehlt
            ? { ausgewaehlt: `Ausgewählt (${ausgewaehlt.length})` }
            : {}),
    };

    const create = async () => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(o));
        } catch {
            // ohne Speicher: Wahl gilt nur jetzt
        }
        // Fenster sofort öffnen (sonst blockiert der Browser es nach dem Laden)
        const fenster = window.open('', '_blank');
        if (!fenster) {
            setFehler(
                'Der Browser hat das Fenster blockiert. Bitte Pop-ups für diese Seite erlauben.',
            );

            return;
        }
        fenster.document.write('<p style="font-family:sans-serif">Lädt …</p>');
        setLaden(true);
        setFehler(null);
        try {
            const res = await fetch(quelle, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                throw new Error(String(res.status));
            }
            const { zeilen } = (await res.json()) as { zeilen: PrintRow[] };
            const ids =
                auswahl === 'gefiltert'
                    ? gefiltert
                    : auswahl === 'ausgewaehlt'
                      ? ausgewaehlt
                      : undefined;
            const rows = ids
                ? zeilen.filter(
                      (z) => ids.includes(z.id) || ids.includes(z.slug),
                  )
                : zeilen;
            printGlossary(rows, o, farben, titel, fenster);
            onOpenChange(false);
        } catch {
            fenster.close();
            setFehler('Die Begriffe konnten nicht geladen werden.');
        } finally {
            setLaden(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>PDF erstellen</DialogTitle>
                    <DialogDescription>
                        Danach im Druckdialog «Als PDF speichern» wählen. Das
                        PDF enthält den Lizenzhinweis CC BY 4.0.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4">
                    <Wahl
                        id="pdf-auswahl"
                        label="Begriffe"
                        value={auswahl}
                        options={auswahlOptionen}
                        disabled={(k) =>
                            k === 'ausgewaehlt' && !ausgewaehlt?.length
                        }
                        onChange={setAuswahl}
                    />
                    <Wahl
                        id="pdf-format"
                        label={CHOICES.format.label}
                        value={o.format}
                        options={CHOICES.format.options}
                        onChange={(v) => set('format', v)}
                    />
                    <div className="grid gap-1">
                        <Wahl
                            id="pdf-umfang"
                            label={CHOICES.umfang.label}
                            value={o.umfang}
                            options={CHOICES.umfang.options}
                            onChange={(v) => set('umfang', v)}
                        />
                        <p className="text-xs text-muted-foreground sm:pl-[8.375rem]">
                            {HINT[o.umfang]}
                        </p>
                    </div>
                    <Wahl
                        id="pdf-gruppe"
                        label={CHOICES.gruppe.label}
                        value={o.gruppe}
                        options={CHOICES.gruppe.options}
                        onChange={(v) => set('gruppe', v)}
                    />
                    <Wahl
                        id="pdf-umbruch"
                        label={CHOICES.umbruch.label}
                        value={o.umbruch}
                        options={CHOICES.umbruch.options}
                        disabled={() => o.gruppe === 'keine'}
                        onChange={(v) => set('umbruch', v)}
                    />
                    {fehler ? (
                        <p className="text-sm text-destructive" role="alert">
                            {fehler}
                        </p>
                    ) : null}
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Abbrechen
                    </Button>
                    <Button onClick={() => void create()} disabled={laden}>
                        <FileDown /> {laden ? 'Lädt …' : 'Erstellen'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
