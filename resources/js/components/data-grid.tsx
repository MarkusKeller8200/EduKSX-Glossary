import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
    type CSSProperties,
    type KeyboardEvent,
    type PointerEvent as ReactPointerEvent,
    type ReactNode,
} from 'react';
import { cn } from '@/lib/utils';

/**
 * Tabellen-Baustein der Administration (#103, #108): Sortieren per Klick auf den Spaltenkopf (auch mit Tastatur,
 * `aria-sort`), Spaltenbreite mit dem Splitter am rechten Rand des Kopfs (Maus oder Pfeiltasten), Breiten und
 * Sortierung im Browser gemerkt (je Tabelle `id`). Beim horizontalen Scrollen bleiben die Schlüsselspalte links
 * (`sticky: 'left'`) und die Aktionsspalte rechts (`sticky: 'right'`) sichtbar. Optional mit Gruppen (Zwischenzeilen).
 * Breiten nach Inhalt (#128): Spalten ohne von Hand gezogene Breite misst der Baustein im Browser-Layout – beim
 * ersten Anzeigen, nach Grössenänderungen der Tabelle und wenn sich der Inhalt ändert (Suche, Filter, Gruppen).
 * Doppelklick auf den Splitter passt eine von Hand gezogene Spalte wieder an den Inhalt an.
 */
export type GridColumn<T> = {
    id: string;
    header: ReactNode;
    /** Text für Bildschirmleser und Splitter, wenn `header` kein Text ist */
    label?: string;
    cell: (row: T) => ReactNode;
    /** Wert zum Sortieren; ohne Angabe ist die Spalte nicht sortierbar */
    sortValue?: (row: T) => string | number | null | undefined;
    /** Breite in px, falls nicht nach Inhalt gemessen werden kann */
    width?: number;
    minWidth?: number;
    /** Breite fest (z. B. Auswahl), kein Splitter */
    fixed?: boolean;
    sticky?: 'left' | 'right';
    /** nimmt den freien Platz auf, wenn die Tabelle breiter ist als die Spalten (Vorgabe: breiteste Textspalte) */
    grow?: boolean;
    className?: string;
    headerClassName?: string;
    /** `translate="no"` für Daten (Abkürzungen) */
    noTranslate?: boolean;
};

export type GridGroup<T> = { key: string; title: ReactNode; rows: T[] };

type Sort = { id: string; desc: boolean } | null;
type Stored = { widths?: Record<string, number>; sort?: Sort };

const STORAGE = 'glossary.grid.';
const collator = new Intl.Collator('de-CH', {
    numeric: true,
    sensitivity: 'base',
});

function load(id: string): Stored {
    try {
        return JSON.parse(localStorage.getItem(STORAGE + id) ?? '{}') as Stored;
    } catch {
        return {};
    }
}

function save(id: string, value: Stored) {
    try {
        localStorage.setItem(STORAGE + id, JSON.stringify(value));
    } catch {
        // ohne Speicher: gilt nur bis zum Neuladen
    }
}

function compare(
    a: string | number | null | undefined,
    b: string | number | null | undefined,
) {
    const empty = (v: unknown) => v === null || v === undefined || v === '';
    if (empty(a) && empty(b)) {
        return 0;
    }
    // leere Werte immer ans Ende
    if (empty(a)) {
        return 1;
    }
    if (empty(b)) {
        return -1;
    }
    if (typeof a === 'number' && typeof b === 'number') {
        return a - b;
    }

    return collator.compare(String(a), String(b));
}

export default function DataGrid<T>({
    id,
    columns,
    rows,
    groups,
    getRowId,
    empty,
    caption,
    initialSort = null,
    rowClassName,
}: {
    /** Schlüssel zum Merken von Breiten und Sortierung */
    id: string;
    columns: GridColumn<T>[];
    rows?: T[];
    /** statt `rows`: Gruppen mit Zwischenzeile; sortiert wird innerhalb der Gruppen */
    groups?: GridGroup<T>[];
    getRowId: (row: T) => string;
    empty?: ReactNode;
    caption?: string;
    initialSort?: Sort;
    rowClassName?: (row: T) => string | undefined;
}) {
    const [stored] = useState<Stored>(() => load(id));
    const [widths, setWidths] = useState<Record<string, number>>(
        stored.widths ?? {},
    );
    const [sort, setSort] = useState<Sort>(
        stored.sort !== undefined ? stored.sort : initialSort,
    );

    useEffect(() => {
        save(id, { widths, sort });
    }, [id, widths, sort]);

    // nach Inhalt gemessene Breiten (#128), nicht gespeichert
    const [measured, setMeasured] = useState<Record<string, number>>({});
    const headRefs = useRef<Record<string, HTMLTableCellElement | null>>({});
    const measuring = columns.some(
        (c) =>
            !c.fixed &&
            widths[c.id] === undefined &&
            measured[c.id] === undefined,
    );
    const columnKey = columns.map((c) => c.id).join('|');

    useLayoutEffect(() => {
        if (!measuring) {
            return;
        }
        const next: Record<string, number> = {};
        for (const c of columns) {
            const el = headRefs.current[c.id];
            if (!c.fixed && widths[c.id] === undefined && el) {
                next[c.id] = Math.max(
                    c.minWidth ?? 60,
                    Math.min(640, Math.ceil(el.getBoundingClientRect().width)),
                );
            }
        }
        setMeasured((old) => ({ ...old, ...next }));
        // nur beim ersten Anzeigen bzw. wenn Spalten wechseln oder eine Breite zurückgesetzt wird
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [measuring, columnKey]);

    const width = useCallback(
        (c: GridColumn<T>) => widths[c.id] ?? measured[c.id] ?? c.width ?? 160,
        [widths, measured],
    );
    const fit = (id: string) => {
        setWidths(({ [id]: _drop, ...rest }) => rest);
        setMeasured(({ [id]: _drop, ...rest }) => rest);
    };
    const sorter = useMemo(() => {
        const col = columns.find((c) => c.id === sort?.id);
        if (!sort || !col?.sortValue) {
            return null;
        }
        const value = col.sortValue;

        return (list: T[]) =>
            [...list].sort(
                (a, b) => compare(value(a), value(b)) * (sort.desc ? -1 : 1),
            );
    }, [columns, sort]);

    const sections: GridGroup<T>[] = useMemo(() => {
        const base = groups ?? [{ key: '', title: '', rows: rows ?? [] }];

        return sorter
            ? base.map((g) => ({ ...g, rows: sorter(g.rows) }))
            : base;
    }, [groups, rows, sorter]);
    const count = sections.reduce((n, g) => n + g.rows.length, 0);

    // Inhalt geändert (#128): neu messen. Signatur aus Gruppen und Zeilen-IDs statt Referenz, damit neu berechnete,
    // aber gleiche Listen keine Schleife auslösen.
    const dataKey = useMemo(
        () =>
            sections
                .map(
                    (g) =>
                        `${g.key}:${g.rows.length}:${g.rows
                            .slice(0, 200)
                            .map(getRowId)
                            .join(',')}`,
                )
                .join('|'),
        [sections, getRowId],
    );
    const lastData = useRef(dataKey);
    useEffect(() => {
        if (lastData.current !== dataKey) {
            lastData.current = dataKey;
            setMeasured({});
        }
    }, [dataKey]);

    // Grösse der Tabelle geändert (Fenster, Ansicht): neu messen, kurz verzögert
    const wrapper = useRef<HTMLDivElement | null>(null);
    useEffect(() => {
        const el = wrapper.current;
        if (!el || typeof ResizeObserver === 'undefined') {
            return;
        }
        let last = el.clientWidth;
        let timer: ReturnType<typeof setTimeout> | undefined;
        const observer = new ResizeObserver(() => {
            const now = el.clientWidth;
            if (Math.abs(now - last) < 2) {
                return;
            }
            last = now;
            clearTimeout(timer);
            timer = setTimeout(() => setMeasured({}), 150);
        });
        observer.observe(el);

        return () => {
            clearTimeout(timer);
            observer.disconnect();
        };
    }, []);

    const toggle = (c: GridColumn<T>) => {
        if (!c.sortValue) {
            return;
        }
        setSort((s) =>
            s?.id !== c.id
                ? { id: c.id, desc: false }
                : s.desc
                  ? null
                  : { id: c.id, desc: true },
        );
    };

    const total = columns.reduce((n, c) => n + width(c), 0);
    // Spalte für den freien Platz (#123): ausdrücklich gewählt, sonst die breiteste ohne feste Breite und Fixierung
    const growId =
        columns.find((c) => c.grow)?.id ??
        columns
            .filter((c) => !c.fixed && !c.sticky)
            .reduce<GridColumn<T> | null>(
                (best, c) => (!best || width(c) > width(best) ? c : best),
                null,
            )?.id;
    // Abstand der rechten fixierten Spalten
    const rightOffset = (index: number) =>
        columns
            .slice(index + 1)
            .filter((c) => c.sticky === 'right')
            .reduce((n, c) => n + width(c), 0);
    const leftOffset = (index: number) =>
        columns
            .slice(0, index)
            .filter((c) => c.sticky === 'left')
            .reduce((n, c) => n + width(c), 0);
    const stickyStyle = (c: GridColumn<T>, index: number): CSSProperties =>
        c.sticky === 'left'
            ? { position: 'sticky', left: leftOffset(index) }
            : c.sticky === 'right'
              ? { position: 'sticky', right: rightOffset(index) }
              : {};
    const stickyClass = (c: GridColumn<T>) =>
        c.sticky
            ? cn(
                  'z-10 bg-background',
                  c.sticky === 'left'
                      ? 'shadow-[inset_-1px_0_0_var(--border)]'
                      : 'shadow-[inset_1px_0_0_var(--border)]',
              )
            : '';

    return (
        <div
            ref={wrapper}
            className="overflow-x-auto rounded-lg border border-border"
        >
            <table
                className="text-sm"
                // mindestens so breit wie der Platz (#123); die Spalte growId nimmt den Rest auf
                style={
                    measuring
                        ? { tableLayout: 'auto', width: '100%' }
                        : {
                              tableLayout: 'fixed',
                              width: `max(${total}px, 100%)`,
                          }
                }
            >
                {caption ? (
                    <caption className="sr-only">{caption}</caption>
                ) : null}
                <colgroup>
                    {columns.map((c) => (
                        <col
                            key={c.id}
                            style={
                                measuring
                                    ? c.fixed || widths[c.id] !== undefined
                                        ? { width: width(c) }
                                        : {}
                                    : {
                                          width:
                                              c.id === growId
                                                  ? undefined
                                                  : width(c),
                                          minWidth: width(c),
                                      }
                            }
                        />
                    ))}
                </colgroup>
                <thead className="text-left">
                    <tr>
                        {columns.map((c, i) => {
                            const active = sort?.id === c.id;
                            const ariaSort = !c.sortValue
                                ? undefined
                                : active
                                  ? sort?.desc
                                      ? 'descending'
                                      : 'ascending'
                                  : 'none';

                            return (
                                <th
                                    key={c.id}
                                    ref={(el) => {
                                        headRefs.current[c.id] = el;
                                    }}
                                    scope="col"
                                    aria-sort={ariaSort}
                                    style={stickyStyle(c, i)}
                                    className={cn(
                                        'relative bg-muted px-3 py-2 font-medium',
                                        c.sticky && 'z-20',
                                        c.headerClassName,
                                    )}
                                >
                                    {c.sortValue ? (
                                        <button
                                            type="button"
                                            onClick={() => toggle(c)}
                                            className="inline-flex max-w-full items-center gap-1 rounded-sm text-left hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <span className="truncate">
                                                {c.header}
                                            </span>
                                            {active ? (
                                                sort?.desc ? (
                                                    <ArrowDown
                                                        className="size-3.5 shrink-0"
                                                        aria-hidden
                                                    />
                                                ) : (
                                                    <ArrowUp
                                                        className="size-3.5 shrink-0"
                                                        aria-hidden
                                                    />
                                                )
                                            ) : (
                                                <ArrowUpDown
                                                    className="size-3.5 shrink-0 opacity-40"
                                                    aria-hidden
                                                />
                                            )}
                                        </button>
                                    ) : (
                                        c.header
                                    )}
                                    {c.fixed ? null : (
                                        <Splitter
                                            label={
                                                c.label ??
                                                (typeof c.header === 'string'
                                                    ? c.header
                                                    : c.id)
                                            }
                                            width={width(c)}
                                            min={c.minWidth ?? 60}
                                            onChange={(w) =>
                                                setWidths((old) => ({
                                                    ...old,
                                                    [c.id]: w,
                                                }))
                                            }
                                            onFit={() => fit(c.id)}
                                        />
                                    )}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                {sections.map((g) => (
                    <tbody key={g.key || 'alle'}>
                        {g.title ? (
                            <tr className="border-t border-border bg-muted/50">
                                <th
                                    scope="colgroup"
                                    colSpan={columns.length}
                                    className="px-3 py-2 text-left font-semibold"
                                >
                                    <span className="sticky left-3">
                                        {g.title}{' '}
                                        <span className="font-normal text-muted-foreground">
                                            ({g.rows.length})
                                        </span>
                                    </span>
                                </th>
                            </tr>
                        ) : null}
                        {g.rows.map((r) => (
                            <tr
                                key={`${g.key}-${getRowId(r)}`}
                                className={cn(
                                    'border-t border-border align-top',
                                    rowClassName?.(r),
                                )}
                            >
                                {columns.map((c, i) => (
                                    <td
                                        key={c.id}
                                        style={stickyStyle(c, i)}
                                        translate={
                                            c.noTranslate ? 'no' : undefined
                                        }
                                        className={cn(
                                            'px-3 py-2 break-words',
                                            stickyClass(c),
                                            c.className,
                                        )}
                                    >
                                        {c.cell(r)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                ))}
                {count === 0 ? (
                    <tbody>
                        <tr>
                            <td
                                colSpan={columns.length}
                                className="px-3 py-6 text-center text-muted-foreground"
                            >
                                {empty ?? 'Keine Einträge.'}
                            </td>
                        </tr>
                    </tbody>
                ) : null}
            </table>
        </div>
    );
}

/**
 * Splitter am rechten Rand des Spaltenkopfs: ziehen mit der Maus, Pfeiltasten ±16 px; Doppelklick bzw. Enter passt
 * die Breite an den Inhalt an (#128).
 */
function Splitter({
    label,
    width,
    min,
    onChange,
    onFit,
}: {
    label: string;
    width: number;
    min: number;
    onChange: (width: number) => void;
    onFit: () => void;
}) {
    const start = useRef<{ x: number; w: number } | null>(null);
    const clamp = (w: number) => Math.max(min, Math.min(1200, Math.round(w)));

    const down = (e: ReactPointerEvent<HTMLDivElement>) => {
        e.preventDefault();
        e.currentTarget.setPointerCapture(e.pointerId);
        start.current = { x: e.clientX, w: width };
    };
    const move = (e: ReactPointerEvent<HTMLDivElement>) => {
        if (start.current) {
            onChange(clamp(start.current.w + e.clientX - start.current.x));
        }
    };
    const up = () => {
        start.current = null;
    };
    const key = (e: KeyboardEvent<HTMLDivElement>) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            e.preventDefault();
            onChange(clamp(width + (e.key === 'ArrowRight' ? 16 : -16)));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            onFit();
        }
    };

    return (
        <div
            role="separator"
            aria-orientation="vertical"
            aria-label={`Breite der Spalte «${label}»`}
            aria-valuenow={width}
            aria-valuemin={min}
            aria-valuemax={1200}
            tabIndex={0}
            onPointerDown={down}
            onPointerMove={move}
            onPointerUp={up}
            onPointerCancel={up}
            onKeyDown={key}
            onDoubleClick={onFit}
            title="Ziehen: Breite ändern · Doppelklick: an Inhalt anpassen"
            className="absolute top-0 right-0 h-full w-2 cursor-col-resize touch-none border-r-2 border-transparent hover:border-border focus-visible:border-ring focus-visible:outline-none"
        />
    );
}
