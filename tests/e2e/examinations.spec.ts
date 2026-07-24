import { test, expect } from '@playwright/test';
import { login, xsrfHeader } from './helpers';

test.describe('Configure exam → input marks → process result → view marksheet', () => {
    test('a processed exam produces a passing marksheet row for the student', async ({ page }) => {
        await login(page);
        const headers = await xsrfHeader(page);
        const stamp = Date.now();

        const years = await (await page.request.get('/api/v1/academic/academic-years?per_page=200', { headers })).json();
        const yearId = years.data[0].id;
        const classConfigs = await (await page.request.get('/api/v1/academic/class-configs', { headers })).json();
        const classConfig = classConfigs.data[0];
        const subjects = await (await page.request.get('/api/v1/academic/subjects?per_page=200', { headers })).json();
        const subjectId = subjects.data[0].id;

        // Grade scale is per class_id — only seed it once (repeat runs would hit the overlap guard).
        const existingGrades = await (await page.request.get(`/api/v1/examinations/grades?class_id=${classConfig.class_id}`, { headers })).json();
        if (existingGrades.data.length === 0) {
            const scale = [
                { name: 'A+', grade_point: 5.0, mark_from: 80, mark_to: 100 },
                { name: 'A', grade_point: 4.0, mark_from: 70, mark_to: 79.99 },
                { name: 'F', grade_point: 0.0, mark_from: 0, mark_to: 69.99 },
            ];
            for (const g of scale) {
                const res = await page.request.post('/api/v1/examinations/grades', { headers, data: { class_id: classConfig.class_id, ...g } });
                expect(res.ok()).toBeTruthy();
            }
        }

        const exam = await (await page.request.post('/api/v1/examinations/exams', { headers, data: { name: `E2E Exam ${stamp}`, type: 'monthly' } })).json();
        const written = await (await page.request.post('/api/v1/examinations/short-codes', { headers, data: { name: `Written ${stamp}` } })).json();
        const mcq = await (await page.request.post('/api/v1/examinations/short-codes', { headers, data: { name: `MCQ ${stamp}` } })).json();

        const markConfigSave = await page.request.post('/api/v1/examinations/mark-configs', {
            headers,
            data: {
                class_config_id: classConfig.id,
                exam_id: exam.data.id,
                items: [
                    { subject_id: subjectId, short_code_id: written.data.id, total_marks: 70, pass_mark: 23 },
                    { subject_id: subjectId, short_code_id: mcq.data.id, total_marks: 30, pass_mark: 10 },
                ],
            },
        });
        expect(markConfigSave.ok()).toBeTruthy();

        const uid = `E2E-EXM-${stamp}`;
        const studentName = `Exam Test Student ${stamp}`;
        const studentRes = await page.request.post('/api/v1/students', {
            headers,
            data: {
                student_uid: uid,
                name: studentName,
                sex: 'male',
                fathers_name: 'Father',
                mothers_name: 'Mother',
                academic_year_id: yearId,
                class_config_id: classConfig.id,
                roll: `${stamp}`.slice(-4),
            },
        });
        expect(studentRes.ok(), await studentRes.text()).toBeTruthy();
        const student = await studentRes.json();

        // Marks Input: navigate the real UI to enter Written 56/70 + MCQ 24/30 (80% -> A+, pass).
        await page.goto('/exams');
        await page.getByRole('button', { name: 'Marks Input' }).click();
        const selects = page.locator('select');
        await selects.nth(0).selectOption({ label: years.data[0].name });
        await selects.nth(1).selectOption({ label: classConfig.label });
        await selects.nth(2).selectOption({ label: `E2E Exam ${stamp}` });
        await selects.nth(3).selectOption({ label: subjects.data[0].name });

        const row = page.getByRole('row', { name: new RegExp(studentName) });
        await expect(row).toBeVisible();
        const marksInputs = row.locator('input[type="number"], input[type="text"]');
        await marksInputs.nth(0).fill('56');
        await marksInputs.nth(1).fill('24');
        const [saveMarksResponse] = await Promise.all([
            page.waitForResponse((r) => r.url().includes('/api/v1/examinations/marks') && r.request().method() === 'POST'),
            page.getByRole('button', { name: /save marks/i }).click(),
        ]);
        expect(saveMarksResponse.ok(), await saveMarksResponse.text()).toBeTruthy();

        // Result processing (separate /results route).
        await page.goto('/results');
        await page.locator('select').nth(0).selectOption({ label: classConfig.label });
        await page.locator('select').nth(1).selectOption({ label: `E2E Exam ${stamp}` });
        await Promise.all([
            page.waitForResponse((r) => r.url().includes('/api/v1/examinations/results/general-process') && r.ok()),
            page.getByRole('button', { name: /run general process/i }).click(),
        ]);
        await expect(page.getByText(/subject results processed/i)).toBeVisible();

        // Marksheet totals/pass-fail come from the merit process (StudentExamSummary), not
        // general process alone — run it too before checking the report.
        await page.locator('select').nth(2).selectOption({ label: classConfig.class_name });
        await page.locator('select').nth(3).selectOption({ label: `E2E Exam ${stamp}` });
        await Promise.all([
            page.waitForResponse((r) => r.url().includes('/api/v1/examinations/results/merit-process') && r.ok()),
            page.getByRole('button', { name: /run merit process/i }).click(),
        ]);
        await expect(page.getByText(/students processed/i)).toBeVisible();

        // Marksheet report shows the processed, passing result.
        await page.getByRole('button', { name: 'Reports', exact: true }).click();
        await page.locator('select').nth(1).selectOption({ label: classConfig.label });
        await page.locator('select').nth(2).selectOption({ label: `E2E Exam ${stamp}` });
        await Promise.all([
            page.waitForResponse((r) => r.url().includes('/api/v1/examinations/results/marksheet') && r.ok()),
            page.getByRole('button', { name: 'Run', exact: true }).click(),
        ]);
        const marksheetRow = page.locator('div.p-5', { hasText: studentName });
        await expect(marksheetRow).toBeVisible();
        await expect(marksheetRow.getByText('Pass', { exact: true })).toBeVisible();
    });
});
