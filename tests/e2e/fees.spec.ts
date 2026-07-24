import { test, expect } from '@playwright/test';
import { login, xsrfHeader } from './helpers';

test.describe('Configure fees → collect → GL voucher posted', () => {
    test('collecting a fee produces a receipt posted to the ledger', async ({ page }) => {
        await login(page);
        const headers = await xsrfHeader(page);
        const stamp = Date.now();

        const years = await (await page.request.get('/api/v1/academic/academic-years?per_page=200', { headers })).json();
        const yearId = years.data[0].id;
        const classConfigs = await (await page.request.get('/api/v1/academic/class-configs', { headers })).json();
        const classConfigId = classConfigs.data[0].id;

        const head = await (await page.request.post('/api/v1/fees/heads', { headers, data: { name: `Tuition ${stamp}` } })).json();
        const subHead = await (await page.request.post('/api/v1/fees/sub-heads', {
            headers, data: { fee_head_id: head.data.id, name: `Monthly Tuition ${stamp}` },
        })).json();

        const configSave = await page.request.post('/api/v1/fees/configs', {
            headers,
            data: { class_config_id: classConfigId, academic_year_id: yearId, items: [{ fee_sub_head_id: subHead.data.id, amount: 500 }] },
        });
        expect(configSave.ok()).toBeTruthy();

        const uid = `E2E-FEE-${stamp}`;
        const student = await (await page.request.post('/api/v1/students', {
            headers,
            data: {
                student_uid: uid,
                name: `Fee Test Student ${stamp}`,
                sex: 'male',
                fathers_name: 'Father',
                mothers_name: 'Mother',
                academic_year_id: yearId,
                class_config_id: classConfigId,
                roll: `${stamp}`.slice(-4),
            },
        })).json();

        const assess = await page.request.post('/api/v1/fees/configs/assess', {
            headers, data: { class_config_id: classConfigId, academic_year_id: yearId },
        });
        expect(assess.ok()).toBeTruthy();

        await page.goto('/fees');
        await page.getByRole('button', { name: 'Collection', exact: true }).click();
        await page.getByRole('button', { name: 'Dues & Collection' }).click();
        await page.locator('select').first().selectOption({ label: `${student.data.name} (${uid})` });

        const row = page.getByRole('row', { name: new RegExp(`Monthly Tuition ${stamp}`) });
        await expect(row).toBeVisible();
        await row.locator('input[type="number"]').fill('500');
        await expect(page.getByText('Total: 500.00')).toBeVisible();
        await Promise.all([
            page.waitForResponse((r) => r.url().includes('/api/v1/fees/collections') && r.request().method() === 'POST' && r.ok()),
            page.getByRole('button', { name: 'Collect', exact: true }).click(),
        ]);

        await expect(page.getByText(/posted to the ledger/i)).toBeVisible({ timeout: 10000 });
    });
});
