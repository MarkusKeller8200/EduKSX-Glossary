import { cn } from '@/lib/utils';

/**
 * Glossary-Symbol aus dem CI (Repo markussxch, docs/brand/icons/eduksx, freigegeben 09.11.2026): offenes Buch mit
 * A–Z. Buch in der Textfarbe der Marke, «A» orange, «Z» blau; wechselt mit hell/dunkel (#239, #251).
 */
export default function GlossaryIcon({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden
            className={cn('size-6 shrink-0', className)}
            fill="none"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path
                d="M12 6.6C9.8 5.1 6.9 4.7 3 5.2v13.3c3.9-.5 6.8-.1 9 1.4 2.2-1.5 5.1-1.9 9-1.4V5.2c-3.9-.5-6.8-.1-9 1.4Z"
                stroke="var(--brand-neutral)"
            />
            <path d="M12 6.6v13.3" stroke="var(--brand-neutral)" />
            <path
                d="M5.6 15.2 7.4 9.6l1.8 5.6M6.2 13.4h2.4"
                stroke="var(--brand-orange)"
            />
            <path d="M14.6 9.8h3.2l-3.2 5.2h3.2" stroke="var(--brand-blue)" />
        </svg>
    );
}
