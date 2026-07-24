import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 2 : undefined,
    reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
    webServer: process.env.E2E_BASE_URL
        ? undefined
        : {
            // Additive/idempotent only — never migrate:fresh here, this runs against a real dev DB.
            command: 'php artisan migrate --force && php artisan db:seed --force && php artisan serve --port=8000',
            url: 'http://127.0.0.1:8000',
            reuseExistingServer: !process.env.CI,
            timeout: 120_000,
        },
});
