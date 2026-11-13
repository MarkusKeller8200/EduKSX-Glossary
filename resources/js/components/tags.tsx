import { usePage } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    isColorName,
    palette,
    stateClasses,
    TERM_STATUS,
    termStatusClasses,
    termStatusValue,
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
    info = false,
}: {
    list: 'kategorien' | 'herkunft';
    label: string;
    className?: string;
    /** Antippen zeigt Bezeichnung, Beschreibung und Link (#258), falls vorhanden */
    info?: boolean;
}) {
    const { farben, labelInfo } = usePage().props;
    const color = farben[list][label];
    const badge = (
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
    const details = info ? labelInfo?.[list]?.[label] : undefined;
    if (!details) {
        return badge;
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className="cursor-help rounded-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    aria-label={`${label}: Erklärung anzeigen`}
                >
                    {badge}
                </button>
            </DialogTrigger>
            <DialogContent className="max-w-sm">
                <DialogHeader>
                    <DialogTitle className="flex flex-wrap items-center gap-2">
                        {badge}
                        {details.name ? <span>{details.name}</span> : null}
                    </DialogTitle>
                    <DialogDescription>
                        {list === 'kategorien' ? 'Kategorie' : 'Herkunft'}
                    </DialogDescription>
                </DialogHeader>
                {details.beschreibung ? (
                    <p className="text-sm whitespace-pre-line">
                        {details.beschreibung}
                    </p>
                ) : null}
                {details.url ? (
                    <a
                        href={details.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex min-w-0 items-center gap-1 text-sm text-link underline-offset-4 hover:underline"
                    >
                        <span className="truncate">{details.url}</span>
                        <ExternalLink
                            className="size-3.5 shrink-0"
                            aria-hidden
                        />
                    </a>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

/** Mehrere Labels einer Liste, immer untereinander (#105). */
export function ListBadges({
    list,
    labels,
    info = false,
}: {
    list: 'kategorien' | 'herkunft';
    labels: string[];
    info?: boolean;
}) {
    if (labels.length === 0) {
        return null;
    }

    return (
        <span className="inline-flex flex-col items-start gap-1">
            {labels.map((l) => (
                <ListBadge key={l} list={list} label={l} info={info} />
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
            {TERM_STATUS[termStatusValue(status)] ?? status}
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
    draft,
}: {
    published: number | null;
    revision: number;
    /** inhaltliche Abweichung vom öffentlichen Stand (DEC-022); ohne Angabe: andere Revisionsnummer */
    draft?: boolean;
}) {
    const entwurf = draft ?? (published === null || published !== revision);

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
            {entwurf ? (
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
