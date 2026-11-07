import { createInertiaApp } from '@inertiajs/react';
import { initializeTheme } from '@/hooks/use-appearance';
import AdminLayout from '@/layouts/admin-layout';
import PublicLayout from '@/layouts/public-layout';

const appName = import.meta.env.VITE_APP_NAME || 'EduKSX-Glossary';

const pages = import.meta.glob('./pages/**/*.tsx');

void createInertiaApp({
    resolve: async (name: string) => {
        const loader = pages[`./pages/${name}.tsx`];
        if (!loader) {
            throw new Error(`Page not found: ${name}`);
        }
        const module = (await loader()) as { default?: unknown };

        return (module.default ?? module) as never;
    },
    title: (title) => (title ? `${title} – ${appName}` : appName),
    layout: (name) =>
        name.startsWith('admin/') ? AdminLayout : PublicLayout,
    strictMode: true,
    progress: {
        color: '#007EBB',
    },
});

// Hell/Dunkel beim Laden setzen (wie im Intranet)
initializeTheme();
