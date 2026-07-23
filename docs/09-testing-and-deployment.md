# 09 — Testing & Deployment (Phase 13 + Deployment Architecture)

## Part A — Testing strategy (Phase 13)

Test pyramid: many fast unit/feature tests, fewer E2E. **Pest** (PHP) + **Vitest/RTL** (React) +
**Playwright** (E2E). CI runs all on every PR; coverage gates on domain logic.

### Backend

**Unit tests** — pure business rules, no DB where possible:
- Result computation: component→subject total, pass/fail vs `pass_mark`/`acceptance`, grade lookup per
  class scale, **GPA with 4th-subject rule**, merit ordering by `merit_process_type`.
- Fee math: payable = config − waiver + fine; due/fine by time-config.
- Accounting: voucher **debit = credit** invariant; trial-balance aggregation.
- Attendance: punch → present/late/absent per time-config; period-wise resolution.
- Roll/seat uniqueness; migration promote/push-back transitions.

**Feature (HTTP) tests** — per endpoint, through the stack:
- **Authentication:** login, lockout, reset, session invalidation.
- **Authorization & branch isolation (critical):** a user of Branch A gets `404/403` on Branch B records;
  permission-gated endpoints reject without the permission; teacher can only enter marks for assigned
  subjects; `report.cross_branch` enforced. **These are the highest-value tests** (multi-tenant safety).
- **Student workflows:** bulk registration (partial-failure reporting), update, migration, reports.
- **Academic setup:** CRUD + branch stamping + `serial/status`.
- **Routine:** create + **conflict detection** (teacher/room/section clash → 409).
- **Exam:** setup→config→mark input (range validation, absent xor mark)→process→results→marksheet.
- **Fees:** config→dues→collection→GL voucher posted (event side-effect asserted).
- **Envelope contract:** success/error shapes, pagination meta, validation `errors` map.

**API tests** — every major endpoint's contract, status codes, filters/sort/pagination, idempotency.

Factories + seeders build a realistic multi-branch fixture; each test isolates via `RefreshDatabase` and
runs under an explicit branch context.

### Frontend
- **Component tests (Vitest + RTL):** DataTable, EditableGrid (paste, per-cell validation), ResourceForm
  (server-error mapping), FilterBar cascades, SetupResource.
- **Permission rendering:** `usePermission` hides/shows nav, buttons, routes; `PermissionRoute` blocks.
- **Form tests:** Zod validation, RHF submission, 422 mapping onto fields.
- **Hook tests:** auth/branch/session store transitions, query invalidation on mutation.

### End-to-end (Playwright)
Critical journeys against a seeded stack:
1. Login → select branch → dashboard.
2. Register students (bulk) → appears in list/report.
3. Configure exam → input marks → process result → view marksheet/tabulation → print.
4. Configure fees → collect fee → due report updates → GL voucher present.
5. Cross-branch isolation: switch branch, confirm data changes; deep-link to other branch record → blocked.
6. Take attendance → absentee SMS queued.

### Quality gates
- CI: `pest`, `vitest`, `playwright`, `phpstan/larastan` (level max on modules), ESLint+TS, `pint`,
  `composer/npm audit`. Coverage threshold on `Modules/*/Domain` & `Application`.
- **Parity harness:** targeted checks comparing rebuild outputs (e.g. a marksheet's computed GPA) against
  known-good values from the legacy app, so business logic is provably preserved (brief Rule 7).

## Part B — Deployment architecture

### Environments
`local` (Sail/Docker) → `staging` (prod-like, seeded) → `production`. Config via `.env`/secrets manager;
no secrets in repo.

### Topology (start simple, scale out)
```
              ┌── CDN (static assets, public site) ──┐
Internet ─ LB/TLS ─▶ Nginx ─▶ PHP-FPM (Laravel API + built React) ×N (stateless)
                        │            │
                        │            ├─▶ MySQL 8 (primary)  [+ read replica when needed]
                        │            ├─▶ Redis (cache + session + queue)
                        │            └─▶ Object storage (S3-compatible: photos, docs, reports)
                        └─▶ Horizon workers ×M (queues) + Scheduler (cron)
```
- **Stateless app servers** (session in Redis) → horizontal scale behind the LB.
- **Horizon** workers for queues; **scheduler** for cron (due-fine accrual, attendance ingestion, digests).
- **Zero-downtime deploys** (atomic release symlink / rolling) with `migrate --force`, `config:cache`,
  `route:cache`, `view:cache`, `queue:restart`, Vite build.
- React is built and served as static assets (Vite) via the same origin (simplifies Sanctum same-site).

### CI/CD
GitHub Actions: lint → static analysis → tests (PHP+JS+E2E) → build → deploy (staging auto, prod gated).
DB migrations reviewed; destructive migrations require explicit approval.

### Observability & ops
- Structured JSON logs → aggregator; **Sentry** (API + React) for errors; Horizon dashboard for queues.
- Uptime + latency + queue-depth alerts; slow-query log.
- **Backups:** automated MySQL backups (PITR/binlog) + object-storage versioning; periodic restore drills.
- **Tenancy safety in ops:** backup/restore and data exports always branch-aware; support tooling logs PII access.

### Rollout / data migration from legacy
- Because the legacy app is Laravel+MySQL, a **data migration** path exists: map legacy tables → new
  schema via one-off importers (validated against the crawl's field inventory). Run per branch, verify
  with the parity harness, cut over branch-by-branch. (Detailed migration plan is a Phase-0 deliverable
  once we confirm DB access — see open questions, doc 10.)
