import { test, expect } from '@playwright/test';
import { login, xsrfHeader } from './helpers';

test.describe('Take attendance', () => {
    test('marks a class present and saves', async ({ page }) => {
        await login(page);
        const headers = await xsrfHeader(page);

        const years = await (await page.request.get('/api/v1/academic/academic-years?per_page=200', { headers })).json();
        const yearId = years.data[0].id;
        const classConfigs = await (await page.request.get('/api/v1/academic/class-configs', { headers })).json();
        const classConfigId = classConfigs.data[0].id;
        const className = classConfigs.data[0].label as string;

        const uid = `E2E-ATT-${Date.now()}`;
        const created = await page.request.post('/api/v1/students', {
            headers,
            data: {
                student_uid: uid,
                name: 'Attendance Test Student',
                sex: 'male',
                fathers_name: 'Father',
                mothers_name: 'Mother',
                academic_year_id: yearId,
                class_config_id: classConfigId,
                roll: `${Date.now()}`.slice(-4),
            },
        });
        expect(created.ok()).toBeTruthy();

        await page.goto('/attendance');
        await page.locator('select').first().selectOption({ label: className });
        await expect(page.getByText('Attendance Test Student').first()).toBeVisible();

        await page.getByRole('button', { name: /mark all present/i }).click();
        const [response] = await Promise.all([
            page.waitForResponse((r) => r.url().includes('/api/v1/attendance/students/take') && r.request().method() === 'POST'),
            page.getByRole('button', { name: /save attendance/i }).click(),
        ]);
        expect(response.ok()).toBeTruthy();
        await expect(page.getByRole('button', { name: /^saved$/i })).toBeVisible();
    });
});
