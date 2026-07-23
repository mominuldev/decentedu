// Read-only authenticated crawler for the existing Safe Eduman app.
// Captures, for every reachable screen: rendered HTML, a full-page screenshot,
// and the API/network traffic (requests + JSON responses). Produces manifests
// summarising pages, API endpoints, and the detected tech stack.
//
// SAFETY: navigation is GET-only (page.goto on same-origin links). The crawler
// never submits forms other than login and never clicks buttons, so it cannot
// create/update/delete data. Logout/delete/export links are explicitly skipped.

import { chromium } from 'playwright';
import 'dotenv/config';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

// ----------------------------- config ---------------------------------------
const BASE_URL = (process.env.BASE_URL || 'https://safeeduman.com').replace(/\/+$/, '');
const EMAIL = process.env.LOGIN_EMAIL;
const PASSWORD = process.env.LOGIN_PASSWORD;
const OUTPUT_DIR = path.resolve(process.env.OUTPUT_DIR || './crawl-output');
const HEADLESS = String(process.env.HEADLESS || 'false').toLowerCase() === 'true';
const MAX_PAGES = parseInt(process.env.MAX_PAGES || '250', 10);
const DELAY = parseInt(process.env.CRAWL_DELAY_MS || '600', 10);
const EXTRA_SKIP = (process.env.EXTRA_SKIP || '').split(',').map(s => s.trim()).filter(Boolean);

const SEL_EMAIL = process.env.SEL_EMAIL || '';
const SEL_PASSWORD = process.env.SEL_PASSWORD || '';
const SEL_SUBMIT = process.env.SEL_SUBMIT || '';

if (!EMAIL || !PASSWORD) {
  console.error('\nERROR: LOGIN_EMAIL and LOGIN_PASSWORD must be set in .env (copy .env.example).\n');
  process.exit(1);
}

// URL substrings that must never be visited (destructive / session-ending / noise).
const SKIP_SUBSTRINGS = [
  'logout', 'log-out', 'signout', 'sign-out', 'delete', 'destroy', 'remove',
  'forgot-password', 'reset-password', 'password/reset', 'impersonate',
  'export', 'download', 'print', '.pdf', '.csv', '.xlsx', '.zip', '.xls',
  'mailto:', 'tel:', 'javascript:', '#',
  ...EXTRA_SKIP,
];

const origin = new URL(BASE_URL).origin;

// ----------------------------- helpers --------------------------------------
const dirs = {
  root: OUTPUT_DIR,
  html: path.join(OUTPUT_DIR, 'html'),
  shots: path.join(OUTPUT_DIR, 'screenshots'),
  net: path.join(OUTPUT_DIR, 'network'),
};
for (const d of Object.values(dirs)) fs.mkdirSync(d, { recursive: true });

const slug = (u) => {
  const url = new URL(u);
  let s = (url.pathname + url.search).replace(/^\/+/, '').replace(/[^a-zA-Z0-9]+/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
  if (!s) s = 'root';
  if (s.length > 80) s = s.slice(0, 60) + '_' + crypto.createHash('md5').update(u).digest('hex').slice(0, 8);
  return s;
};

const shouldSkip = (u) => {
  const low = u.toLowerCase();
  if (SKIP_SUBSTRINGS.some(sub => sub && low.includes(sub))) return true;
  try { if (new URL(u, BASE_URL).origin !== origin) return true; } catch { return true; }
  return false;
};

// Normalise a URL for dedupe: drop hash, keep path+query (filters matter).
const norm = (u) => {
  try {
    const url = new URL(u, BASE_URL);
    url.hash = '';
    return url.origin + url.pathname + url.search;
  } catch { return null; }
};

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

// --------------------------- network capture --------------------------------
// Keyed per-page; we swap the active buffer before each navigation.
let activeNet = [];
const apiCatalog = new Map(); // "METHOD /path" -> {count, statuses:Set, sampleFile}

function templatePath(pathname) {
  // Replace numeric ids and long hashes with :id for a cleaner endpoint catalog.
  return pathname
    .split('/')
    .map(seg => (/^\d+$/.test(seg) ? ':id' : /^[0-9a-f]{16,}$/i.test(seg) ? ':hash' : seg))
    .join('/');
}

// ------------------------------ login ---------------------------------------
async function login(page) {
  console.log(`→ Opening ${BASE_URL}/login`);
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded', timeout: 45000 });

  const emailSel = SEL_EMAIL || 'input[type="email"], input[name="email"], input[name="username"], input#email';
  const passSel = SEL_PASSWORD || 'input[type="password"], input[name="password"], input#password';
  const submitSel = SEL_SUBMIT || 'button[type="submit"], input[type="submit"], button:has-text("Sign in")';

  await page.fill(emailSel, EMAIL, { timeout: 20000 });
  await page.fill(passSel, PASSWORD, { timeout: 20000 });

  await Promise.all([
    page.waitForLoadState('networkidle', { timeout: 45000 }).catch(() => {}),
    page.click(submitSel, { timeout: 20000 }),
  ]);
  await sleep(1500);

  const url = page.url();
  if (/login/i.test(url)) {
    const err = await page.locator('text=/invalid|incorrect|failed|error|credentials/i').first().textContent().catch(() => null);
    throw new Error(`Login appears to have failed (still on ${url}).${err ? ' Message: ' + err.trim() : ''}\nCheck credentials, or set SEL_EMAIL/SEL_PASSWORD/SEL_SUBMIT in .env.`);
  }
  console.log(`✓ Logged in. Landed on ${url}`);
  return url;
}

// --------------------------- tech fingerprint -------------------------------
async function fingerprint(context, page, landingUrl) {
  const cookies = await context.cookies();
  const info = {
    landingUrl,
    cookies: cookies.map(c => c.name),
    generatorMeta: null,
    assets: [],
    inertia: false,
    livewire: false,
    react: false,
    vue: false,
  };
  try {
    info.generatorMeta = await page.getAttribute('meta[name="generator"]', 'content').catch(() => null);
    const html = await page.content();
    info.inertia = /data-page=|@inertiajs|inertia/i.test(html);
    info.livewire = /wire:id|livewire|@livewire/i.test(html);
    info.react = /id="root"|react|__REACT/i.test(html);
    info.vue = /data-v-|__vue__|id="app"/i.test(html);
    const assetMatches = [...html.matchAll(/(?:src|href)="([^"]*\/(?:build|assets|js|css|dist)\/[^"]+)"/gi)];
    info.assets = [...new Set(assetMatches.map(m => m[1]))].slice(0, 60);
  } catch {}
  return info;
}

// ------------------------------ crawl ---------------------------------------
async function extractLinks(page) {
  return page.evaluate(() => {
    const out = new Set();
    for (const a of document.querySelectorAll('a[href]')) {
      const href = a.getAttribute('href');
      if (href) out.add(href);
    }
    // Also capture common SPA nav elements that use data-href / router links.
    for (const el of document.querySelectorAll('[data-href],[href]')) {
      const h = el.getAttribute('data-href') || el.getAttribute('href');
      if (h) out.add(h);
    }
    return [...out];
  });
}

async function capturePage(page, url) {
  const s = slug(url);
  // HTML
  const html = await page.content();
  fs.writeFileSync(path.join(dirs.html, `${s}.html`), html);
  // Screenshot (full page, best-effort)
  await page.screenshot({ path: path.join(dirs.shots, `${s}.png`), fullPage: true, timeout: 20000 }).catch(() => {});
  // Network for this page
  fs.writeFileSync(path.join(dirs.net, `${s}.json`), JSON.stringify(activeNet, null, 2));
  // Page title + visible headings for the manifest
  const meta = await page.evaluate(() => ({
    title: document.title,
    h1: [...document.querySelectorAll('h1,h2')].map(h => h.textContent.trim()).filter(Boolean).slice(0, 8),
    tables: document.querySelectorAll('table').length,
    forms: document.querySelectorAll('form').length,
  })).catch(() => ({}));
  return { slug: s, ...meta, apiCalls: activeNet.length };
}

async function main() {
  const browser = await chromium.launch({ headless: HEADLESS, channel: 'chrome' }).catch(async () => {
    console.log('(Chrome channel unavailable, falling back to bundled Chromium)');
    return chromium.launch({ headless: HEADLESS });
  });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const page = await context.newPage();

  // Global network listener → routes into the active per-page buffer.
  page.on('response', async (resp) => {
    try {
      const req = resp.request();
      const rurl = resp.url();
      if (new URL(rurl).origin !== origin) return; // only same-origin traffic
      const rtype = req.resourceType();
      if (!['xhr', 'fetch', 'document'].includes(rtype)) return;
      const ct = (resp.headers()['content-type'] || '');
      const isJson = ct.includes('json');
      const entry = {
        method: req.method(),
        url: rurl,
        status: resp.status(),
        resourceType: rtype,
        contentType: ct,
      };
      if (isJson) {
        const body = await resp.text().catch(() => null);
        if (body && body.length < 200000) { try { entry.json = JSON.parse(body); } catch { entry.bodyRaw = body.slice(0, 2000); } }
        else if (body) entry.bodyTruncated = true;
        // catalog
        const key = `${req.method()} ${templatePath(new URL(rurl).pathname)}`;
        const c = apiCatalog.get(key) || { count: 0, statuses: new Set(), sample: rurl };
        c.count++; c.statuses.add(resp.status());
        apiCatalog.set(key, c);
      }
      activeNet.push(entry);
    } catch {}
  });

  const landing = await login(page);
  const fp = await fingerprint(context, page, landing);
  fs.writeFileSync(path.join(dirs.root, 'tech-fingerprint.json'), JSON.stringify(fp, null, 2));
  console.log(`✓ Tech fingerprint saved. Cookies: ${fp.cookies.join(', ') || '(none)'}`);

  // BFS
  const queue = [];
  const seen = new Set();
  const manifest = [];
  const seed = norm(landing) || `${BASE_URL}/dashboard`;
  queue.push(seed); seen.add(seed);
  // also seed common roots in case nav is JS-rendered
  for (const p of ['/dashboard', '/home']) {
    const n = norm(BASE_URL + p);
    if (n && !seen.has(n)) { seen.add(n); queue.push(n); }
  }

  let visited = 0;
  while (queue.length && visited < MAX_PAGES) {
    const url = queue.shift();
    if (shouldSkip(url)) continue;
    activeNet = []; // reset per-page network buffer
    try {
      const resp = await page.goto(url, { waitUntil: 'networkidle', timeout: 40000 }).catch(() => page.goto(url, { waitUntil: 'domcontentloaded', timeout: 40000 }));
      await sleep(400); // let late XHRs land
      // If we got bounced to login, session died — stop.
      if (/\/login/i.test(page.url()) && !/\/login/i.test(url)) {
        console.log('! Bounced to login — session ended. Stopping crawl.');
        break;
      }
      const info = await capturePage(page, url);
      manifest.push({ url, status: resp ? resp.status() : null, ...info });
      visited++;
      console.log(`[${visited}/${Math.min(MAX_PAGES, seen.size)}] ${url}  (${info.apiCalls} api calls, ${info.tables || 0} tables)`);

      // discover new links
      const links = await extractLinks(page);
      for (const raw of links) {
        const n = norm(raw);
        if (!n || seen.has(n) || shouldSkip(n)) continue;
        seen.add(n);
        queue.push(n);
      }
    } catch (e) {
      console.log(`  x failed ${url}: ${e.message.split('\n')[0]}`);
    }
    await sleep(DELAY);
  }

  // write manifests
  fs.writeFileSync(path.join(dirs.root, 'pages-manifest.json'), JSON.stringify(manifest, null, 2));
  const apiOut = [...apiCatalog.entries()].map(([k, v]) => ({ endpoint: k, count: v.count, statuses: [...v.statuses], sample: v.sample })).sort((a, b) => a.endpoint.localeCompare(b.endpoint));
  fs.writeFileSync(path.join(dirs.root, 'api-catalog.json'), JSON.stringify(apiOut, null, 2));

  // human-readable summary
  const summary = [
    `# Safe Eduman crawl summary`,
    ``,
    `- Base URL: ${BASE_URL}`,
    `- Pages captured: ${manifest.length}`,
    `- Distinct API endpoints: ${apiOut.length}`,
    `- Session cookies: ${fp.cookies.join(', ') || '(none)'}`,
    `- Fingerprint: inertia=${fp.inertia} livewire=${fp.livewire} react=${fp.react} vue=${fp.vue}`,
    ``,
    `## Pages`,
    ...manifest.map(m => `- [${m.status}] ${m.url}  —  "${m.title || ''}"  (${m.tables || 0} tables, ${m.forms || 0} forms, ${m.apiCalls} api)`),
    ``,
    `## API endpoints`,
    ...apiOut.map(a => `- ${a.endpoint}  (${a.count}x, statuses ${a.statuses.join('/')})`),
    ``,
  ].join('\n');
  fs.writeFileSync(path.join(dirs.root, 'SUMMARY.md'), summary);

  await browser.close();
  console.log(`\n✓ Done. ${manifest.length} pages, ${apiOut.length} API endpoints.`);
  console.log(`  Artifacts in: ${OUTPUT_DIR}`);
  console.log(`  Start with:   ${path.join(OUTPUT_DIR, 'SUMMARY.md')}\n`);
}

main().catch(e => { console.error('\nFATAL:', e.message, '\n'); process.exit(1); });
