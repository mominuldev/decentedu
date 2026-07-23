# Safe Eduman analysis crawler

Read-only tool that logs into the existing Safe Eduman app with **your** credentials
and captures everything I need to reverse-engineer its architecture: rendered HTML,
full-page screenshots, and the API/network traffic for every reachable screen.

It is safe: it only performs GET navigations (it clicks nothing that mutates data)
and explicitly skips logout / delete / export links.

## Run it

```bash
cd ~/Sites/decentedu/analysis-crawler

# 1. install deps (Playwright + dotenv)
npm install

# 2. add your credentials (this file is git-ignored, stays on your machine)
cp .env.example .env
#   then edit .env and set LOGIN_EMAIL and LOGIN_PASSWORD

# 3. crawl
npm run crawl
```

A Chrome window will open, log in, and walk the app. Watch the console for progress.

## Output

Everything lands in `./crawl-output/`:

| Path                     | What it is                                                  |
|--------------------------|------------------------------------------------------------|
| `SUMMARY.md`             | Human-readable overview — **read this first**              |
| `tech-fingerprint.json`  | Detected stack (Inertia/Livewire/React/Vue, cookies, assets)|
| `pages-manifest.json`    | Every page: URL, title, headings, table/form counts        |
| `api-catalog.json`       | De-duplicated list of API endpoints the frontend calls     |
| `html/*.html`            | Rendered HTML per page                                      |
| `screenshots/*.png`      | Full-page screenshot per page                               |
| `network/*.json`         | Full request/response (incl. JSON bodies) per page          |

When it finishes, tell me and I'll analyze `crawl-output/`.

## If login fails

The crawler auto-detects standard email/password/submit fields. If your form is
non-standard, set `SEL_EMAIL`, `SEL_PASSWORD`, `SEL_SUBMIT` in `.env` to CSS selectors.

## Tuning

See the comments in `.env.example` (`MAX_PAGES`, `HEADLESS`, `CRAWL_DELAY_MS`,
`EXTRA_SKIP`).
