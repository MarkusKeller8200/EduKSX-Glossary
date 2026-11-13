import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.tsx',
                'resources/js/api-docs.ts',
                'resources/js/swagger.ts',
            ],
            refresh: true,
            // Schrift nach CI MarKusSXCH: Inter, eingebunden wie im Intranet (Bunny Fonts); Selbst-Hosting prüfen (KONZEPT-DESIGN)
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                    // Ohne Zusatzpaket «fontaine»: Fallback ist system-ui (app.css --font-sans)
                    optimizedFallbacks: false,
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
            'openapi/**',
            // Vom Blueprint erzeugt bzw. überschrieben (apply.py, release.py) – nicht umformatieren
            'CONTRIBUTING.md',
            'SECURITY.md',
            'CHANGELOG.md',
            'AGENTS.md',
            'docs/GITHUB-ABLAUF.md',
            'docs/UNTERSTUETZEN.md',
            // Reduzierte Lesefassung für GitHub Pages (Daten werden exportiert)
            'site/**',
            // aus dem Code erzeugt (php artisan glossary:datenklassen, #276)
            'docs/DATENKLASSEN.md',
            // gebautes Script zur Einbindung (#155), Hash für SRI muss gleich bleiben
            'public/embed/**',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
