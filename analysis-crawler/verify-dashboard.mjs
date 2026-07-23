import { chromium } from 'playwright';

const url = 'http://localhost:8000';
const out = '/private/tmp/claude-501/-Users-mominul-Sites-decentedu/4b8159b9-e37b-44e6-a726-72d7817ec3ae/scratchpad';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
const page = await ctx.newPage();
const errors = [];
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message));

await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
await page.waitForTimeout(1200); // let charts render
const appHtml = await page.$eval('#app', (el) => el.innerHTML.length).catch(() => 0);
await page.screenshot({ path: out + '/dash-light.png', fullPage: true });

// dark theme
await page.evaluate(() => { localStorage.setItem('decentedu-theme', 'dark'); location.reload(); });
await page.waitForLoadState('networkidle');
await page.waitForTimeout(1200);
await page.screenshot({ path: out + '/dash-dark.png', fullPage: true });

console.log('app innerHTML length:', appHtml);
console.log('console errors:', errors.length ? JSON.stringify(errors.slice(0, 10), null, 2) : 'none');
await browser.close();
