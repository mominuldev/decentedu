import { chromium } from 'playwright';

const base = 'http://localhost:8000';
const out = '/private/tmp/claude-501/-Users-mominul-Sites-decentedu/4b8159b9-e37b-44e6-a726-72d7817ec3ae/scratchpad';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 950 } });
const page = await ctx.newPage();
const errors = [];
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message));

// login
await page.goto(base + '/login', { waitUntil: 'networkidle' });
await page.click('button[type=submit]');
await page.waitForURL(base + '/', { timeout: 15000 }).catch(() => {});
await page.waitForTimeout(800);

// go to academic
await page.goto(base + '/academic', { waitUntil: 'networkidle' });
await page.waitForTimeout(900);
const classRows = await page.locator('table tbody tr').count();
console.log('Classes tab rows:', classRows);
await page.screenshot({ path: out + '/academic-classes.png', fullPage: false });

// Class Config tab
await page.getByRole('button', { name: 'Class Config' }).click();
await page.waitForTimeout(900);
const cfgRows = await page.locator('table tbody tr').count();
console.log('Class Config rows:', cfgRows);
await page.screenshot({ path: out + '/academic-classconfig.png', fullPage: false });

// CRUD: add a Section
await page.getByRole('button', { name: 'Sections' }).click();
await page.waitForTimeout(600);
const before = await page.locator('table tbody tr').count();
await page.getByRole('button', { name: /Add section/i }).click();
await page.waitForTimeout(400);
const uniq = 'D-' + Date.now().toString().slice(-4);
await page.locator('.fixed.z-50 input[type=text]').first().fill(uniq);
await page.screenshot({ path: out + '/academic-modal.png', fullPage: false });
await page.getByRole('button', { name: /Create section/i }).click();
await page.waitForTimeout(1200);
const after = await page.locator('table tbody tr').count();
const added = await page.locator(`table tbody tr:has-text("${uniq}")`).count();
console.log(`Sections before=${before} after=${after} newRowVisible=${added > 0}`);

console.log('console errors:', errors.length ? JSON.stringify(errors.filter((e) => !e.includes('401')).slice(0, 8), null, 2) : 'none');
await browser.close();
