import { usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    isColorName,
    palette,
    stateClasses,
    termStatusClasses,
} from '@/lib/colors';
import { cn } from '@/lib/utils';

/**
 * Farbige Labels nach dem Farbkonzept des Intranets (#77, #91, #92; vgl. `option-badge.tsx` in sek-intranet).
 * Kategorien und Herkunft in der gewählten Farbe (ohne Farbe: slate), Status und Veröffentlichung mit festen Farben.
 */
export function ListBadge({
    list,
    label,
    className,
}: {
    list: 'kategorien' | 'herkunft';
    label: string;
    className?: string;
}) {
    const { farben } = usePage().props;
    const color = farben[list][label];

    return (
        <Badge
            variant="outline"
            className={cn(
                palette[isColorName(color) ? color : 'slate'].badge,
                className,
            )}
        >
            {label}
        </Badge>
    );
}

/** Mehrere Labels einer Liste, immer untereinander (#105). */
export function ListBadges({
    list,
    labels,
}: {
    list: 'kategorien' | 'herkunft';
    labels: string[];
}) {
    if (labels.length === 0) {
        return null;
    }

    return (
        <span className="inline-flex flex-col items-start gap-1">
            {labels.map((l) => (
                <ListBadge key={l} list={list} label={l} />
            ))}
        </span>
    );
}

/** Status des Begriffs: aktuell, historisch, zu prüfen (#91). */
export function StatusBadge({
    status,
    className,
}: {
    status: string;
    className?: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn(termStatusClasses(status), className)}
        >
            {status === 'zu_pruefen' ? 'zu prüfen' : status}
        </Badge>
    );
}

/**
 * Veröffentlichung als «V<Revision>» (#92). Ohne Veröffentlichung «Entwurf»; mit späteren Änderungen zusätzlich
 * «Entwurf V<Revision>».
 */
export function RevisionBadges({
    published,
    revision,
}: {
    published: number | null;
    revision: number;
}) {
    return (
        <span className="inline-flex flex-wrap gap-1">
            {published !== null ? (
                <Badge
                    variant="outline"
                    className={stateClasses.published}
                    title={`Veröffentlicht: Revision ${published}`}
                >
                    V{published}
                </Badge>
            ) : null}
            {published === null || published !== revision ? (
                <Badge
                    variant="outline"
                    className={stateClasses.draft}
                    title={`Entwurf: Revision ${revision}, nicht veröffentlicht`}
                >
                    Entwurf V{revision}
                </Badge>
            ) : null}
        </span>
    );
}

/** Zustand in der Redaktion: inaktiv, zusammengeführt (#91). */
export function StateBadge({
    state,
    children,
}: {
    state: 'inactive' | 'merged';
    children: string;
}) {
    return (
        <Badge variant="outline" className={stateClasses[state]}>
            {children}
        </Badge>
    );
}
