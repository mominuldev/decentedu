# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A ground-up rebuild of "Safe Eduman," a multi-branch school/college ERP for the Bangladeshi education
system, currently a legacy Laravel+Livewire+AdminLTE app. This repo is the new stack: **Laravel 13 API +
React 19 SPA (TypeScript) + Tailwind v4 + MySQL**, built as a modular monolith. The legacy app was
reverse-engineered via an authenticated crawler (`analysis-crawler/crawl-output/`) and the findings /
architecture decisions are written up in `docs/01` through `docs/10` (see `docs/README.md` for the
index) — read the relevant doc before touching a module you don't already understand, since the "why"
behind schema and API shape choices lives there, not in code comments.

Build phases (tracked in `docs/10-roadmap-risks-open-questions.md`): Phase 1 Foundation, Phase 2
Organizations & Branches, Phase 3 Academic Foundation, Phase 4 People Management (Students/HR), Phase 5
Routines & Attendance, Phase 6 Examinations & Results — **all done**. Phase 7 (Finance/Accounting) is
next; Phase 8 (Comms/Credentials/CMS) after that. Routes not yet built render as `Placeholder` stubs in
`router.tsx`.

## Commands

```bash
# Full dev environment (Laravel server + queue listener + log tailer + Vite), one command:
composer run dev

# Backend only
php artisan serve

# Frontend only
npm run dev
npm run build

# Tests (whole suite, sqlite :memory: — see phpunit.xml)
composer run test
php artisan test

# Single test file / filter
php artisan test --filter=BranchScopingTest
php artisan test tests/Feature/ClassConfigTest.php

# Lint/format PHP
vendor/bin/pint

# DB
php artisan migrate
php artisan migrate:fresh --seed   # DatabaseSeeder chains Organization/Branch, Academic, Student, HR, Routine/Attendance, Exam seeders
```

There is no JS test runner or linter configured yet (no `test`/`lint` script in `package.json`); rely on
`tsc` (via the editor/`vite build`) for type errors.

## Backend architecture

- **Feature-first organization**, not layer-first: controllers under `app/Http/Controllers/Api/<Module>/`
  (Academic, Students, Hr, Routines, Attendance, Examinations) and models under `app/Models/<Module>/`.
  A handful of setup-style resources (academic years, classes, shifts, sections, groups, categories,
  subjects, designations, hr-sections, exams, short-codes) are served through one generic
  `{Module}\SetupController` behind a `{resource}` route parameter constrained to a slug whitelist — see
  `routes/api.php` — rather than one controller per trivial CRUD resource. Add new trivial lookup tables
  to that whitelist instead of writing a new controller.
- **Multi-branch tenancy is row-level, single-DB**: every tenant-scoped model uses the
  `App\Models\Concerns\BelongsToBranch` trait, which adds a global `branch_id` scope and auto-stamps
  `branch_id` on create from `App\Support\BranchContext`. `BranchContext` is a per-request singleton set
  once by the `branch` middleware (`App\Http\Middleware\EnsureBranchContext`, aliased in
  `bootstrap/app.php`), which resolves the active branch from the session (falling back to the user's
  default branch) and also sets it as the Spatie permissions "team id." All authenticated `v1` routes run
  behind `auth:sanctum` + `branch` (see the route group in `routes/api.php`).
  - Cross-branch/reporting queries must opt out explicitly: `Model::withoutGlobalScope('branch')` or the
    `withoutBranchScope()` local scope.
  - Mutations that write `branch_id` should call `BranchContext::idOrFail()` (aborts 409 if no branch is
    active) rather than trusting client input.
- **RBAC**: `spatie/laravel-permission` with branch-scoped roles via the "teams" feature (team id = active
  branch id, wired in `EnsureBranchContext`). See `docs/05-multitenancy-and-rbac.md`.
- **Auth**: Sanctum SPA cookie sessions (not tokens) — `csrf-cookie` then `auth/login`; see
  `resources/js/lib/api.ts` for the client side (`withCredentials`, `withXSRFToken`).
- **API envelope**: every endpoint responds through `App\Support\ApiResponse::success()/error()`, giving a
  consistent `{ success, message, data, meta }` / `{ success:false, message, error_code, errors }` shape.
  Use it rather than raw `response()->json()`.
- **Result processing / reports** (marksheet, tabulation, merit/fail lists, admit cards) live in
  `Examinations\ResultController` / `AdmitController` — these are the highest-complexity module
  (`docs/06-api-architecture.md`, `docs/08-security-performance-reporting.md`) and are meant to be
  queue-backed for anything bulk; check for a `Job` before adding a synchronous heavy loop.

## Frontend architecture

- Entry is `resources/js/main.tsx`; routes are centralized in `resources/js/app/router.tsx` using
  `createBrowserRouter`. Every real page is wrapped via the `page()` helper (`ProtectedRoute` +
  `DashboardLayout`); unbuilt routes use `stub()` → `features/misc/Placeholder` labeled with its phase.
  When building a new module's UI, add its route here rather than leaving it orphaned.
- Feature-first layout under `resources/js/features/<module>/` (auth, dashboard, academic, students, hr,
  routines, attendance, examinations) — one folder per backend module, mirroring the API side.
- Path alias `@/*` → `resources/js/*` (configured in both `vite.config.js` and `tsconfig.json`).
- Shared HTTP client is the single axios instance in `resources/js/lib/api.ts` (cookie-based session,
  XSRF header) plus `toApiError()` to normalize failures into the `{ message, error_code?, errors? }`
  shape — use these instead of instantiating axios elsewhere.
- Server state via **TanStack Query**; forms are expected to use React Hook Form + Zod per
  `docs/07-frontend-system-design.md` (schema-driven, matching API validation rules).

## Working with the legacy analysis

`analysis-crawler/` holds the read-only crawl of the production legacy app (`crawl-output/`: page
manifest, rendered HTML, screenshots, network logs, field reports) that grounds every "confirmed" claim
in the `docs/` blueprint. Treat it as historical reference/evidence, not as code to run or modify.