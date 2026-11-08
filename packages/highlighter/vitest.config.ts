import { resolve } from 'node:path';
import { defineConfig } from 'vitest/config';

// Tests des Kerns (#155) und der Browser-Erweiterung (#153) ohne Laravel-Plugins; DOM über happy-dom
export default defineConfig({
    test: {
        root: resolve(import.meta.dirname, '../..'),
        environment: 'happy-dom',
        include: [
            'packages/**/test/**/*.test.ts',
            'extension/test/**/*.test.ts',
        ],
    },
});
