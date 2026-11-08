import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * Aktion als Symbol (#108): wenig Platz in Tabellen, Tooltip beim Zeigen bzw. Fokus und `aria-label` für
 * Bildschirmleser. Mit `href` als Link (Inertia), sonst als Knopf.
 */
export default function IconAction({
    icon: Icon,
    label,
    href,
    onClick,
    disabled,
    destructive,
}: {
    icon: LucideIcon;
    label: string;
    href?: string;
    onClick?: () => void;
    disabled?: boolean;
    destructive?: boolean;
}) {
    const cls = destructive ? 'text-destructive hover:text-destructive' : '';

    return (
        <TooltipProvider delayDuration={300}>
            <Tooltip>
                <TooltipTrigger asChild>
                    {href && !disabled ? (
                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className={cls}
                        >
                            <Link href={href} aria-label={label}>
                                <Icon aria-hidden />
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className={cls}
                            aria-label={label}
                            disabled={disabled}
                            onClick={onClick}
                        >
                            <Icon aria-hidden />
                        </Button>
                    )}
                </TooltipTrigger>
                <TooltipContent>{label}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
