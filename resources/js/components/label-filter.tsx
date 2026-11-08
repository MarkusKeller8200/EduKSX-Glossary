import { usePage } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { isColorName, palette } from '@/lib/colors';
import { cn } from '@/lib/utils';

/**
 * Filter nach Kategorie bzw. Herkunft als farbige Labels in der Farbe der Liste (#145). Mehrfachauswahl
 * (Begriffe mit einem der gewählten Labels), «Zurücksetzen». Gewählte Labels mit Rahmen und Haken, für
 * Bildschirmleser `aria-pressed`.
 */
export default function LabelFilter({
    list,
    title,
    labels,
    selected,
    onChange,
}: {
    list: 'kategorien' | 'herkunft';
    title: string;
    labels: string[];
    selected: string[];
    onChange: (selected: string[]) => void;
}) {
    const { farben } = usePage().props;
    if (labels.length === 0) {
        return null;
    }
    const toggle = (label: string) =>
        onChange(
            selected.includes(label)
                ? selected.filter((s) => s !== label)
                : [...selected, label],
        );

    return (
        <div
            role="group"
            aria-label={title}
            className="flex flex-wrap items-center gap-1.5"
        >
            <span className="mr-1 text-sm text-muted-foreground">{title}:</span>
            {labels.map((label) => {
                const color = farben[list][label];
                const on = selected.includes(label);

                return (
                    <button
                        key={label}
                        type="button"
                        aria-pressed={on}
                        onClick={() => toggle(label)}
                        className={cn(
                            'rounded-md border px-2 py-0.5 text-xs font-medium transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            palette[isColorName(color) ? color : 'slate'].badge,
                            on
                                ? 'ring-2 ring-foreground/70 ring-offset-1 ring-offset-background'
                                : selected.length > 0 &&
                                      'opacity-60 hover:opacity-100',
                        )}
                    >
                        {on ? '✓ ' : ''}
                        {label}
                    </button>
                );
            })}
            {selected.length > 0 ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => onChange([])}
                    aria-label={`${title}: Auswahl zurücksetzen`}
                >
                    <RotateCcw aria-hidden /> Zurücksetzen
                </Button>
            ) : null}
        </div>
    );
}
