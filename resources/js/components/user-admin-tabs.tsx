import { Link } from '@inertiajs/react';

/** Reiter «Benutzer | Organisationen | Anfragen» in der Administration (#413, #460): echte Seiten, damit Links und Zurück funktionieren. */
export default function UserAdminTabs({
    aktiv,
}: {
    aktiv: 'benutzer' | 'organisationen' | 'anfragen';
}) {
    const tabs = [
        { key: 'benutzer', label: 'Benutzer', href: '/admin/benutzer' },
        {
            key: 'organisationen',
            label: 'Organisationen',
            href: '/admin/organisationen',
        },
        // Bewerbungen als Moderator/in (#460)
        { key: 'anfragen', label: 'Anfragen', href: '/admin/anfragen' },
    ] as const;

    return (
        <nav
            aria-label="Benutzer und Organisationen"
            className="mt-4 flex flex-wrap gap-2 border-b"
        >
            {tabs.map((t) => (
                <Link
                    key={t.key}
                    href={t.href}
                    aria-current={aktiv === t.key ? 'page' : undefined}
                    className={`-mb-px border-b-2 px-3 py-2 text-sm font-medium ${
                        aktiv === t.key
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    }`}
                >
                    {t.label}
                </Link>
            ))}
        </nav>
    );
}
