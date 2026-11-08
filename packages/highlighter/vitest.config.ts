import { defineConfig } from 'vitest/config';

// Tests des Kerns (#155) ohne Laravel-Plugins; DOM über happy-dom
export default defineConfig({
    test: {
        root: 'packages/highlighter',
        environment: 'happy-dom',
        include: ['test/**/*.test.ts'],
    },
});
