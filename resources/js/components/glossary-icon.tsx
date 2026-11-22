import { EduksxIcon } from '@eduksx/ui';
import { cn } from '@/lib/utils';

/**
 * Glossary-Symbol aus dem EduKSX-Icon-Set (einzige Quelle: Repo EduKSX-UI, Paket @eduksx/ui): offenes Buch mit A–Z.
 * Farben über die Markenwerte (--brand-*), wechselt mit hell/dunkel (#239, #251). Keine eigene Kopie der Pfade.
 */
export default function GlossaryIcon({ className }: { className?: string }) {
    return (
        <EduksxIcon
            name="glossary"
            className={cn('size-6 shrink-0', className)}
        />
    );
}
