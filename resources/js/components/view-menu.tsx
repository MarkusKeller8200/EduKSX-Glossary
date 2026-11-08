import { ChevronDown, RotateCcw, SlidersHorizontal } from 'lucide-react';
import type { GridSort } from '@/components/data-grid';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type ViewLayout = 'table' | 'cards';

/**
 * Ein Bedienelement für Darstellung (Tabelle/Kacheln), Gruppierung und Sortierung mit «Ansicht zurücksetzen»
 * (#143, nach dem Glossar im Intranet, dort als einzelne Menüs). Zeigt im Knopf, was von der Vorgabe abweicht.
 */
export default function ViewMenu({
    layout,
    onLayout,
    groups,
    group,
    onGroup,
    sorts,
    sort,
    onSort,
    changed,
    onReset,
}: {
    layout: ViewLayout;
    onLayout: (layout: ViewLayout) => void;
    groups: { value: string; label: string }[];
    group: string;
    onGroup: (group: string) => void;
    sorts: { id: string; label: string }[];
    sort: GridSort;
    onSort: (sort: GridSort) => void;
    /** weicht von der Vorgabe ab */
    changed: boolean;
    onReset: () => void;
}) {
    const groupLabel = groups.find((g) => g.value === group)?.label;
    const sortLabel = sorts.find((s) => s.id === sort?.id)?.label;
    const summary = [
        layout === 'cards' ? 'Kacheln' : null,
        group ? `gruppiert: ${groupLabel}` : null,
        sortLabel ? `${sortLabel} ${sort?.desc ? '↓' : '↑'}` : null,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    aria-label={`Ansicht${summary ? `: ${summary}` : ''}`}
                >
                    <SlidersHorizontal aria-hidden />
                    Ansicht
                    {summary ? (
                        <span className="hidden max-w-56 truncate text-xs font-normal text-muted-foreground sm:inline">
                            {summary}
                        </span>
                    ) : null}
                    <ChevronDown aria-hidden className="opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="max-h-[75vh] w-64 overflow-y-auto"
            >
                <DropdownMenuLabel>Darstellung</DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={layout}
                    onValueChange={(v) => onLayout(v as ViewLayout)}
                >
                    <DropdownMenuRadioItem value="table">
                        Tabelle
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem value="cards">
                        Kacheln
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
                <DropdownMenuSeparator />
                <DropdownMenuLabel>Gruppierung</DropdownMenuLabel>
                <DropdownMenuRadioGroup value={group} onValueChange={onGroup}>
                    {groups.map((g) => (
                        <DropdownMenuRadioItem
                            key={g.value || 'keine'}
                            value={g.value}
                        >
                            {g.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
                <DropdownMenuSeparator />
                <DropdownMenuLabel>Sortierung</DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={sort?.id ?? ''}
                    onValueChange={(v) =>
                        onSort(v ? { id: v, desc: sort?.desc ?? false } : null)
                    }
                >
                    <DropdownMenuRadioItem value="">
                        keine
                    </DropdownMenuRadioItem>
                    {sorts.map((s) => (
                        <DropdownMenuRadioItem key={s.id} value={s.id}>
                            {s.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
                {sort ? (
                    <DropdownMenuRadioGroup
                        value={sort.desc ? 'desc' : 'asc'}
                        onValueChange={(v) =>
                            onSort({ id: sort.id, desc: v === 'desc' })
                        }
                    >
                        <DropdownMenuSeparator />
                        <DropdownMenuRadioItem value="asc">
                            aufsteigend
                        </DropdownMenuRadioItem>
                        <DropdownMenuRadioItem value="desc">
                            absteigend
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                ) : null}
                <DropdownMenuSeparator />
                <DropdownMenuItem disabled={!changed} onSelect={onReset}>
                    <RotateCcw aria-hidden /> Ansicht zurücksetzen
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
