import { test, expect } from '@playwright/test';
import { login, switchBranch, DEMO_EMAIL } from './helpers';

test.describe('Login → select branch → dashboard', () => {
    test('signs in and lands on the dashboard', async ({ page }) => {
        await login(page);
        await expect(page.getByRole('heading', { name: /good (morning|afternoon|evening)/i })).toBeVisible();
    });

    test('rejects an invalid password and stays on the login page', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email', { exact: true }).fill(DEMO_EMAIL);
        await page.getByLabel('Password', { exact: true }).fill('wrong-password');
        await page.getByRole('button', { name: /sign in to registry/i }).click();
        await expect(page.getByText(/credentials do not match|too many attempts/i).first()).toBeVisible();
        await expect(page).toHaveURL(/\/login/);
    });

    test('can switch the active branch and the dashboard reflects it', async ({ page }) => {
        await login(page);
        await switchBranch(page, 'Demo College');
        await expect(page.getByText('Demo College')).toBeVisible();
    });

    test('logs out back to the login page', async ({ page }) => {
        await login(page);
        await page.getByRole('button', { name: /sign out/i }).click();
        await expect(page).toHaveURL(/\/login/);
    });
});
