import { cn } from '@/lib/utils';

/**
 * Wortmarke nach CI MarKusSXCH (CI-Manual v1.0, Abschnitte 3, 4 und 10; Markenfortschreibung v1.1).
 * Segmente in fester Reihenfolge, Farben nur über Tokens, Gesamtname für Bildschirmleser, Segmente dekorativ.
 * Neuer Baustein des Glossary – an die GUI-Prototypen des Intranets zurückmelden (docs/KONZEPT-DESIGN.md).
 */
type Token = 'violet' | 'orange' | 'blue' | 'neutral';

type Segment = { text: string; token: Token };

const WORDMARKS = {
    developer: [
        { text: 'M', token: 'violet' },
        { text: 'ar', token: 'neutral' },
        { text: 'K', token: 'orange' },
        { text: 'us', token: 'neutral' },
        { text: 'SXCH', token: 'blue' },
    ],
    education: [
        { text: 'E', token: 'violet' },
        { text: 'du', token: 'neutral' },
        { text: 'K', token: 'orange' },
        { text: 'SX', token: 'blue' },
    ],
} satisfies Record<string, Segment[]>;

const COLOR: Record<Token, string> = {
    violet: 'text-brand-violet',
    orange: 'text-brand-orange',
    blue: 'text-brand-blue',
    neutral: 'text-brand-neutral',
};

export type BrandKey = keyof typeof WORDMARKS;

export default function BrandWordmark({
    brand,
    suffix,
    className,
}: {
    brand: BrandKey;
    /** Produktzusatz, neutral nach Bindestrich, z. B. «Glossary» → «EduKSX-Glossary» */
    suffix?: string;
    className?: string;
}) {
    const segments: Segment[] = [
        ...WORDMARKS[brand],
        ...(suffix ? [{ text: `-${suffix}`, token: 'neutral' as const }] : []),
    ];
    const name = segments.map((s) => s.text).join('');

    return (
        <span
            role="img"
            aria-label={name}
            translate="no"
            className={cn('font-semibold whitespace-nowrap', className)}
        >
            {segments.map((s, i) => (
                <span key={i} aria-hidden="true" className={COLOR[s.token]}>
                    {s.text}
                </span>
            ))}
        </span>
    );
}
