import { ChevronDown } from 'lucide-react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * Einfaches Auswahlfeld des Browsers im Stil des Intranets. Für kurze Listen, in Tabellenzellen und auf
 * dem Handy (öffnet die Auswahl des Geräts). GUI-Prototyp «Dropdown».
 */
function NativeSelect({ className, children, ...props }: ComponentProps<'select'>) {
    return (
        <div className={cn('relative w-full', className)}>
            <select
                {...props}
                className="h-9 w-full appearance-none rounded-md border border-input bg-transparent pr-8 pl-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 aria-invalid:border-destructive"
            >
                {children}
            </select>
            <ChevronDown className="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 opacity-50" aria-hidden />
        </div>
    );
}

export { NativeSelect };
