import type { LucideIcon } from 'lucide-react';
import {
    BookOpen,
    ClipboardCheck,
    Copy,
    FileUp,
    KeyRound,
    LayoutDashboard,
    MessageSquarePlus,
    SearchX,
    Server,
    Settings,
    Tags,
    Users,
} from 'lucide-react';

/** Menü der Administration (#36, #90); Einträge nur mit dem nötigen Recht. Icons für das Flyout-Menü (#259). */
export const ADMIN_NAV: {
    href: string;
    label: string;
    recht: string | null;
    icon: LucideIcon;
}[] = [
    { href: '/admin', label: 'Übersicht', recht: null, icon: LayoutDashboard },
    {
        href: '/admin/begriffe',
        label: 'Begriffe',
        recht: 'edit-terms',
        icon: BookOpen,
    },
    {
        href: '/admin/vorschlaege',
        label: 'Vorschläge',
        recht: 'moderate',
        icon: MessageSquarePlus,
    },
    {
        href: '/admin/doppelte',
        label: 'Doppelte',
        recht: 'edit-terms',
        icon: Copy,
    },
    {
        href: '/admin/suchen-ohne-treffer',
        label: 'Ohne Treffer',
        recht: 'edit-terms',
        icon: SearchX,
    },
    {
        href: '/admin/pruefung',
        label: 'Prüfung',
        recht: 'edit-terms',
        icon: ClipboardCheck,
    },
    { href: '/admin/listen', label: 'Listen', recht: 'lists', icon: Tags },
    {
        href: '/admin/import',
        label: 'Excel-Import',
        recht: 'import',
        icon: FileUp,
    },
    {
        href: '/admin/benutzer',
        label: 'Benutzer',
        recht: 'manage-users',
        icon: Users,
    },
    {
        href: '/admin/servicekonten',
        label: 'Servicekonten',
        recht: 'service-tokens',
        icon: KeyRound,
    },
    {
        href: '/admin/einstellungen',
        label: 'Einstellungen',
        recht: 'settings',
        icon: Settings,
    },
    { href: '/admin/system', label: 'System', recht: 'system', icon: Server },
];
