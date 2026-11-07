/**
 * Farbkonzept (#77, #91, #92) – übernommen aus sek-intranet `resources/js/lib/colors.ts` (Herkunft:
 * docs/UEBERNAHME-INTRANET.md). Je Farbe Klassen für Label (badge), Punkt, Balken, Symbol und Fläche, hell und dunkel
 * mit Kontrast AA. Gespeichert wird nur der Name (App\Support\Palette::NAMES). Die Klassen sind ausgeschrieben, damit
 * Tailwind sie findet.
 */
export const palette = {
    brand: {
        // Glossary: --brand ist Orange (Schrift nur 2.36 : 1) – darum Blau aus --info (4.68 : 1 / 5.87 : 1)
        badge: 'border-info/30 bg-info-soft text-info',
        dot: 'bg-brand',
        bar: 'border-l-brand',
        icon: 'text-brand',
        panel: 'bg-info-soft text-info hover:bg-info-soft/70',
    },
    slate: {
        badge: 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
        dot: 'bg-slate-400',
        bar: 'border-l-slate-400',
        icon: 'text-slate-500',
        panel: 'bg-slate-100 text-slate-800 hover:bg-slate-200/70 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-900',
    },
    sky: {
        badge: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200',
        dot: 'bg-sky-500',
        bar: 'border-l-sky-500',
        icon: 'text-sky-600 dark:text-sky-400',
        panel: 'bg-sky-100 text-sky-800 hover:bg-sky-200/70 dark:bg-sky-950 dark:text-sky-200 dark:hover:bg-sky-900',
    },
    cyan: {
        badge: 'border-cyan-200 bg-cyan-50 text-cyan-800 dark:border-cyan-900 dark:bg-cyan-950 dark:text-cyan-200',
        dot: 'bg-cyan-500',
        bar: 'border-l-cyan-500',
        icon: 'text-cyan-600 dark:text-cyan-400',
        panel: 'bg-cyan-100 text-cyan-800 hover:bg-cyan-200/70 dark:bg-cyan-950 dark:text-cyan-200 dark:hover:bg-cyan-900',
    },
    teal: {
        badge: 'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-200',
        dot: 'bg-teal-500',
        bar: 'border-l-teal-500',
        icon: 'text-teal-600 dark:text-teal-400',
        panel: 'bg-teal-100 text-teal-800 hover:bg-teal-200/70 dark:bg-teal-950 dark:text-teal-200 dark:hover:bg-teal-900',
    },
    emerald: {
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
        dot: 'bg-emerald-500',
        bar: 'border-l-emerald-500',
        icon: 'text-emerald-600 dark:text-emerald-400',
        panel: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200/70 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:bg-emerald-900',
    },
    amber: {
        badge: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
        dot: 'bg-amber-500',
        bar: 'border-l-amber-500',
        icon: 'text-amber-600 dark:text-amber-400',
        panel: 'bg-amber-100 text-amber-800 hover:bg-amber-200/70 dark:bg-amber-950 dark:text-amber-200 dark:hover:bg-amber-900',
    },
    orange: {
        badge: 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-200',
        dot: 'bg-orange-500',
        bar: 'border-l-orange-500',
        icon: 'text-orange-600 dark:text-orange-400',
        panel: 'bg-orange-100 text-orange-800 hover:bg-orange-200/70 dark:bg-orange-950 dark:text-orange-200 dark:hover:bg-orange-900',
    },
    rose: {
        badge: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200',
        dot: 'bg-rose-500',
        bar: 'border-l-rose-500',
        icon: 'text-rose-600 dark:text-rose-400',
        panel: 'bg-rose-100 text-rose-800 hover:bg-rose-200/70 dark:bg-rose-950 dark:text-rose-200 dark:hover:bg-rose-900',
    },
    violet: {
        badge: 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-200',
        dot: 'bg-violet-500',
        bar: 'border-l-violet-500',
        icon: 'text-violet-600 dark:text-violet-400',
        panel: 'bg-violet-100 text-violet-800 hover:bg-violet-200/70 dark:bg-violet-950 dark:text-violet-200 dark:hover:bg-violet-900',
    },
    indigo: {
        badge: 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-900 dark:bg-indigo-950 dark:text-indigo-200',
        dot: 'bg-indigo-500',
        bar: 'border-l-indigo-500',
        icon: 'text-indigo-600 dark:text-indigo-400',
        panel: 'bg-indigo-100 text-indigo-800 hover:bg-indigo-200/70 dark:bg-indigo-950 dark:text-indigo-200 dark:hover:bg-indigo-900',
    },
} as const;

export type ColorName = keyof typeof palette;

export function isColorName(value: unknown): value is ColorName {
    return typeof value === 'string' && value in palette;
}

/** Statusfarben aus den CSS-Tokens (Erfolg, Warnung, Fehler, Hinweis). */
export const statusClasses = {
    success: 'border-success/30 bg-success-soft text-success',
    warning: 'border-warning/30 bg-warning-soft text-warning',
    error: 'border-destructive/30 bg-destructive/10 text-destructive-foreground',
    info: 'border-info/30 bg-info-soft text-info',
} as const;

/**
 * Status eines Begriffs (#91): aktuell grün, zu prüfen orange, historisch grau. Akzeptiert Schlüssel
 * (`zu_pruefen`) und Anzeigetext («zu prüfen»).
 */
export function termStatusClasses(status: string): string {
    switch (status) {
        case 'aktuell':
            return statusClasses.success;
        case 'zu_pruefen':
        case 'zu prüfen':
            return statusClasses.warning;
        default:
            return palette.slate.badge;
    }
}

/** Weitere Zustände in der Redaktion (#91, #92). */
export const stateClasses = {
    published: palette.brand.badge,
    draft: statusClasses.warning,
    inactive: palette.slate.badge,
    merged: palette.violet.badge,
} as const;
