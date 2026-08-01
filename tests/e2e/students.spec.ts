import { test, expect } from '@playwright/test';
import { login, selectOnceLoaded } from './helpers';

test.describe('Register a student and see it in the list', () => {
    test('creates a student via the form and it appears in the students list', async ({ page }) => {
        await login(page);
        await page.getByRole('link', { name: 'Students' }).click();
        await expect(page).toHaveURL(/\/students$/);

        await page.getByRole('button', { name: /add student/i }).click();
        await expect(page).toHaveURL(/\/students\/new/);

        const uid = `E2E-${Date.now()}`;
        const roll = `${Date.now()}`.slice(-6);
        await page.locator('#field-student-uid').fill(uid);
        await page.locator('#field-name').fill('Playwright Test Student');
        await page.locator('#field-birth-certificate-number').fill(`BC-${Date.now()}`);
        await page.locator('#field-father-s-name').fill('Test Father');
        await page.locator('#field-father-s-nid').fill(`FNID-${Date.now()}`);
        await page.locator('#field-father-s-mobile-number').fill('01700000000');
        await page.locator('#field-mother-s-name').fill('Test Mother');
        await page.locator('#field-mother-s-nid').fill(`MNID-${Date.now()}`);
        await selectOnceLoaded(page, 'Academic Year');
        await selectOnceLoaded(page, 'Class');
        await page.locator('#field-roll-number').fill(roll);

        await page.getByRole('button', { name: /create student/i }).first().click();
        await expect(page).toHaveURL(/\/students$/);

        await page.getByPlaceholder(/search by name, uid/i).fill(uid);
        await expect(page.getByText('Playwright Test Student')).toBeVisible();
    });
});

test.describe('Cross-branch isolation', () => {
    test('a student created in one branch cannot be opened while another branch is active', async ({ page }) => {
        await login(page);
        await page.getByRole('link', { name: 'Students' }).click();
        await page.getByRole('button', { name: /add student/i }).click();

        const uid = `E2E-ISO-${Date.now()}`;
        const roll = `${Date.now()}`.slice(-6);
        await page.locator('#field-student-uid').fill(uid);
        await page.locator('#field-name').fill('Isolation Test Student');
        await page.locator('#field-birth-certificate-number').fill(`BC-${Date.now()}`);
        await page.locator('#field-father-s-name').fill('Test Father');
        await page.locator('#field-father-s-nid').fill(`FNID-${Date.now()}`);
        await page.locator('#field-father-s-mobile-number').fill('01700000000');
        await page.locator('#field-mother-s-name').fill('Test Mother');
        await page.locator('#field-mother-s-nid').fill(`MNID-${Date.now()}`);
        await selectOnceLoaded(page, 'Academic Year');
        await selectOnceLoaded(page, 'Class');
        await page.locator('#field-roll-number').fill(roll);
        await page.getByRole('button', { name: /create student/i }).first().click();
        await expect(page).toHaveURL(/\/students\/(\d+)\/edit|\/students$/);

        const url = page.url();
        const editUrl = url.includes('/edit') ? url : null;

        // Switch to a different branch, then try to deep-link to the same student.
        await page.getByTestId('branch-switcher').click();
        await page.getByText('Switch branch').waitFor();
        await page.getByRole('button', { name: 'College' }).click();
        await page.waitForTimeout(300);

        if (editUrl) {
            await page.goto(editUrl);
            await expect(page.getByText(/not found|404/i).or(page.getByText('Isolation Test Student'))).toBeVisible();
            await expect(page.getByText('Isolation Test Student')).not.toBeVisible();
        }

        await page.getByRole('link', { name: 'Students' }).click();
        await page.getByPlaceholder(/search by name, uid/i).fill(uid);
        await expect(page.getByText('Isolation Test Student')).not.toBeVisible();
    });
});
