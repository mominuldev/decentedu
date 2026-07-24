# 11 — Deployment Runbook

Operational companion to [09 — Testing & Deployment](./09-testing-and-deployment.md), written once the
actual deployment artifacts existed (Phase 13). That doc is the design; this one is "what to actually do."

## What exists today

- **`Dockerfile`** — multi-stage build: Node stage compiles the SPA (`public/build`), Composer stage
  installs PHP deps, final stage is `php-fpm` on Alpine with the app + built assets baked in.
- **`docker-compose.yml`** — `nginx` (public entrypoint) → `app` (php-fpm) → `mysql` + `redis`, plus
  `horizon` (queue workers) and `scheduler` (cron loop) as separate long-running containers from the same
  image. `docker/nginx/default.conf` and `docker/php/*.ini` hold the runtime config.
- **`.env.staging.example` / `.env.production.example`** — copy to `.env` on the target host and fill in
  the blanks. Both default to `redis` for cache/session/queue and `stderr` + `JsonFormatter` logging
  (structured JSON on stderr for whatever log aggregator the host ships with).
- **`.github/workflows/ci.yml`** — lint (Pint, ESLint) → static analysis (Larastan) → tests (Pest, Vitest,
  Playwright) → build, on every push/PR. The `e2e` job spins up its own MySQL service container and runs
  the full critical-journey suite (`tests/e2e/`) against a real built SPA + server.
- **`.github/workflows/deploy.yml`** — builds & pushes the Docker image to GHCR, then SSHs into the target
  host and does a `docker compose pull && up -d` + `migrate --force` + cache rebuild + `horizon:terminate`
  (Horizon's supervisor restarts it, picking up new code). Staging deploys automatically on every push to
  `main`; production only runs via manual `workflow_dispatch` and is gated by the `production` GitHub
  Environment's required reviewers.
- **Sentry** — `sentry/sentry-laravel` (backend, wired into `bootstrap/app.php`'s `withExceptions`) and
  `@sentry/react` (frontend, `resources/js/lib/sentry.ts`, called at the top of `main.tsx`). Both no-op
  with no DSN configured, so local dev is unaffected.

## First-time host setup

1. Provision a host with Docker + Docker Compose installed, and DNS pointed at it.
2. `mkdir -p /srv/decentedu && cd /srv/decentedu`, clone the repo (or just copy `docker-compose.yml` +
   `docker/` — the deploy workflow only needs `docker compose` to already know the service shapes).
3. Copy `.env.staging.example` or `.env.production.example` to `.env`, fill in `APP_KEY` (generate with
   `php artisan key:generate --show` locally), DB/Redis passwords, mail, S3, and `SENTRY_LARAVEL_DSN` /
   `VITE_SENTRY_DSN` if you want error tracking from day one.
4. Add a `docker-compose.override.yml` on the host pinning the `image:` the deploy workflow will rewrite
   in place (`sed` targets the `image:` line — see `deploy.yml`).
5. In the GitHub repo: **Settings → Environments**, create `staging` and `production`. Add
   `production`'s required reviewers here — this is the actual "prod is gated" mechanism, not something in
   the workflow file. Add repo **variables** `STAGING_HOST` / `PRODUCTION_HOST` / `DEPLOY_USER` and repo
   **secret** `DEPLOY_SSH_KEY` (a private key whose public half is in the host's `authorized_keys`).
6. First deploy: run the `deploy-staging` job once manually (push to `main`, or `workflow_dispatch` with
   `environment: staging`), then `docker compose exec app php artisan db:seed --force` if you want the demo
   dataset seeded (idempotent — `firstOrCreate` throughout — safe to re-run).

Until step 5 is done, both deploy jobs no-op cleanly (they check for the host variable and skip with a
message) rather than failing CI — the workflow is safe to merge before you've picked a host.

## Zero-downtime deploys

The current approach is container-native, not the classic `atomic release symlink` from docs/09 (that
pattern is for bare-metal/VM deploys with `php-fpm` directly on the host): `docker compose pull` fetches
the new image, `up -d` recreates only the containers whose image changed, and Docker Compose does a
stop-new-start-old handoff per container. Because `nginx` and `mysql`/`redis` aren't touched, there's no
window where the whole stack is down — only a brief gap on the `app` container itself while php-fpm
restarts, which nginx's upstream retry handles for in-flight requests in practice but isn't a hard
guarantee. If that gap ever matters (high traffic), the next step is running 2+ `app` replicas behind
nginx `upstream` load balancing so `docker compose up -d --no-deps --scale app=2 app` can roll one at a
time — not needed yet at this scale.

## Backups

Not yet automated — this is the biggest gap between this doc and docs/09's design. Until it's wired up:

- **MySQL**: the straightforward start is a nightly `docker compose exec mysql mysqldump ...` cron job on
  the host, piped to the object storage bucket already configured for `FILESYSTEM_DISK=s3`. Enable MySQL
  binlogging (`log_bin` in a custom `my.cnf` mounted into the `mysql` service) before relying on this in
  production — without it you only get last-night's snapshot, not point-in-time recovery.
- **Object storage** (`AWS_BUCKET` — photos, TCs, exported reports): enable bucket versioning at the
  provider level; that alone covers accidental overwrite/delete without any app-side work.
- **Restore drills**: not run yet. Before this is a real "backup strategy" rather than aspirational, do
  one full restore (dump → fresh MySQL container → `migrate` skip, load dump → smoke-test login) and time
  it, so there's a known RTO instead of an assumed one.

## Observability

- **Errors**: Sentry, once a DSN is set (see above). No alert routing configured yet — that's a Sentry
  project-settings task (Slack/email/PagerDuty integration), not code.
- **Queues**: Horizon's own dashboard (`/horizon`, gated to the `Super Admin` role via the `viewHorizon`
  Gate in `app/Providers/HorizonServiceProvider.php`) shows job throughput, failures, and wait time per
  queue.
- **Uptime/latency**: nothing wired up yet. The app already exposes Laravel's default health route
  (`/up`, configured in `bootstrap/app.php`'s `withRouting(health: '/up')`) — point any external uptime
  checker (even a free one) at `https://<host>/up` as the minimum viable version of this.
- **Slow queries**: not enabled. MySQL's `slow_query_log` + `long_query_time` are the standard first step;
  nothing in this repo depends on them being on, so it's safe to enable at any time.

## Tenancy-safe ops

Every backup, export, and support-tooling action must stay branch-aware — the whole point of the
`branch_id` row-level isolation model (docs/05) is that a leak here is a real breach, not just a bug. In
practice: never restore a partial/single-branch backup into a shared multi-branch database without
re-verifying `branch_id` scoping on every restored row first, and if a future support-tooling script reads
PII across branches for debugging, log that access the same way the audit log already does for in-app
mutations.

## Rollout: legacy → new cutover

Still blocked on doc 10's open question #1 (legacy DB/source access) — the importer mapping legacy tables
to the new schema doesn't exist yet. Once access is available: build one-off importers validated against
the crawl's field inventory (`analysis-crawler/crawl-output/fields-report.json`), run per branch against a
staging copy, verify with a parity harness (spot-check computed values like GPA against known-good legacy
output), then cut branches over one at a time rather than all at once — each branch's cutover is
independent since isolation is row-level, so a bad import on one branch doesn't block the others.
