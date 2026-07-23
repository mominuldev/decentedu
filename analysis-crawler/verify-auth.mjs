import { chromium } from 'playwright';

const base = 'http://localhost:8000';
const out = '/private/tmp/claude-501/-Users-mominul-Sites-decentedu/4b8159b9-e37b-44e6-a726-72d7817ec3ae/scratchpad';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 950 } });
const page = await ctx.newPage();
const errors = [];
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message));

// 1. hitting / while unauthenticated should land on /login
await page.goto(base + '/', { waitUntil: 'networkidle', timeout: 30000 });
await page.waitForTimeout(800);
console.log('after loading "/", url =', page.url());
await page.screenshot({ path: out + '/login-light.png', fullPage: false });

// dark login
await page.evaluate(() => { localStorage.setItem('decentedu-theme', 'dark'); location.reload(); });
await page.waitForLoadState('networkidle');
await page.waitForTimeout(600);
await page.screenshot({ path: out + '/login-dark.png', fullPage: false });
await page.evaluate(() => { localStorage.setItem('decentedu-theme', 'light'); });

// 2. submit the prefilled demo credentials
await page.goto(base + '/login', { waitUntil: 'networkidle' });
await page.waitForTimeout(400);
await page.click('button[type=submit]');
await page.waitForURL(base + '/', { timeout: 15000 }).catch(() => {});
await page.waitForTimeout(1400); // charts + me
console.log('after login, url =', page.url());
const hasGreeting = await page.locator('text=Good morning').count();
const branchLabel = await page.locator('text=Demo IT School').first().count();
console.log('dashboard greeting present:', hasGreeting > 0, '| branch chip present:', branchLabel > 0);
await page.screenshot({ path: out + '/after-login.png', fullPage: false });

console.log('console errors:', errors.length ? JSON.stringify(errors.slice(0, 8), null, 2) : 'none');
await browser.close();
