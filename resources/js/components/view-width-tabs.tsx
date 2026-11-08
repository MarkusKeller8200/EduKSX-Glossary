import type { LucideIcon } from 'lucide-react';
import { Laptop, Maximize2, MoveHorizontal, Smartphone } from 'lucide-react';
import type { ViewWidth } from '@/hooks/use-view-width';
import { useViewWidth } from '@/hooks/use-view-width';
import { cn } from '@/lib/utils';

/**
 * Umschalter der Ansicht (#76, #142, #144) als Symbole: Mobil, Darstellungsoptimiert, Breit, Voll. Eigene Symbole,
 * nicht verwechselbar mit «System» beim Farbschema.
 */
export default function ViewWidthTabs({
    className = '',
}: {
    className?: string;
}) {
    const { view, setView } = useViewWidth();
    const tabs: { value: ViewWidth; icon: LucideIcon; label: string }[] = [
        { value: 'mobile', icon: Smartphone, label: 'Mobil' },
        { value: 'narrow', icon: Laptop, label: 'Darstellungsoptimiert' },
        { value: 'wide', icon: MoveHorizontal, label: 'Breit' },
        { value: 'full', icon: Maximize2, label: 'Voll (ganze Breite)' },
    ];

    return (
        <div
            role="group"
            aria-label="Ansicht"
            className={cn(
                'inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800',
                className,
            )}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    type="button"
                    aria-pressed={view === value}
                    aria-label={label}
                    title={`Ansicht: ${label}`}
                    onClick={() => setView(value)}
                    className={cn(
                        'flex items-center rounded-md px-2.5 py-1.5 transition-colors',
                        view === value
                            ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                            : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                    )}
                >
                    <Icon className="h-4 w-4" aria-hidden />
                </button>
            ))}
        </div>
    );
}
