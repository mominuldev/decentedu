import { type Page, expect } from '@playwright/test';

export const DEMO_EMAIL = 'demo@decentedu.test';
export const DEMO_PASSWORD = 'password';

export async function login(page: Page, email = DEMO_EMAIL, password = DEMO_PASSWORD) {
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: /sign in to registry/i }).click();
    await expect(page).toHaveURL('/');
}

export async function switchBranch(page: Page, branchName: string) {
    await page.getByTestId('branch-switcher').click();
    await page.getByText('Switch branch').waitFor();
    await page.getByRole('button', { name: branchName }).click();
}

/** Select an option only once the dropdown's options have actually loaded (they're fetched async). */
export async function selectOnceLoaded(page: Page, label: string, index = 1) {
    const select = page.getByLabel(label, { exact: true });
    await expect.poll(async () => select.locator('option').count()).toBeGreaterThan(index);
    await select.selectOption({ index });
}

/**
 * Headers for authenticated `page.request` calls (mirrors resources/js/lib/api.ts).
 * Sanctum only treats a request as "from the frontend" (and starts a session) when its
 * Referer/Origin matches a stateful domain — page.request doesn't send one by default.
 */
export async function xsrfHeader(page: Page): Promise<Record<string, string>> {
    const cookies = await page.context().cookies();
    const token = cookies.find((c) => c.name === 'XSRF-TOKEN')?.value;
    if (!token) throw new Error('XSRF-TOKEN cookie not set — log in before calling the API directly.');
    return { 'X-XSRF-TOKEN': decodeURIComponent(token), Referer: new URL(page.url()).origin };
}
