import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/test/setup.ts'],
        css: false,
        // tests/e2e is Playwright's suite (its own `test` API) — keep Vitest scoped to
        // component/unit tests under resources/js so it doesn't try to run those too.
        include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
    },
});
