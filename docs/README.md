# Safe Eduman — Rebuild Architecture Blueprint

> Target stack: **Laravel (latest LTS) + React + Tailwind CSS + MySQL**, REST API, Sanctum auth,
> scalable **modular monolith**, feature-first modules.
>
> Status: **DESIGN — awaiting approval. No implementation code, migrations, or components yet.**

This blueprint is the output of a discovery-first engagement. The existing production app at
`https://safeeduman.com` was inspected end-to-end with a read-only authenticated crawler that
captured **250 pages**, their rendered HTML, screenshots, form fields, table columns, and network
traffic. Every "confirmed" statement below is grounded in that captured evidence
(`analysis-crawler/crawl-output/`). Proposals and improvements are labelled separately and never
mixed with confirmed facts.

## How the existing system was analysed

| Artifact | Location | What it gave us |
|---|---|---|
| Page manifest (250 URLs) | `crawl-output/SUMMARY.md`, `pages-manifest.json` | Full route surface, titles, table/form counts, HTTP status |
| Rendered HTML per page | `crawl-output/html/*.html` | Sidebar menu tree, forms, tables, hidden inputs |
| Screenshots per page | `crawl-output/screenshots/*.png` | Layout, KPI cards, UX patterns |
| Network log per page | `crawl-output/network/*.json` | XHR endpoints, JSON payload shapes |
| Field report | `crawl-output/../fields-report.json` | Form field names, types, required flags, table columns for 215 pages |
| Tech fingerprint | `crawl-output/tech-fingerprint.json` | Framework, cookies, asset stack |

## Confirmed profile of the existing system

- **What it is:** a multi-branch **school/college management ERP** for the Bangladeshi education
  system (Class/Group/Shift/Section model, 4th-subject, GPA grading, TC/testimonial, Bangla +
  English bilingual). Vendor: "Safe IT Limited." Version **3.0.5**.
- **Stack:** Laravel + **Livewire** + **AdminLTE 3 (Bootstrap 4)** + jQuery + Select2, server-rendered
  Blade, session-cookie auth (`safe_eduman_session`, `XSRF-TOKEN`). Almost certainly **MySQL**.
  It is **not** an API/SPA today — data is delivered by full-page Blade + Livewire round-trips.
- **Scale of surface:** 12 top-level modules, ~180 distinct admin screens.
- **Multi-branch:** one login can hold **many school branches** (demo account sees 5: Demo IT
  School, Demo College, Demo School, Horipur Girls High School, Masud-UL Haque Institute). Users
  pick a working branch on a **"Select Branch"** screen; the active branch + academic session frame
  every screen.
- **RBAC:** roles observed — **Super Admin, Admin** — plus **per-user granular permissions** and
  **per-user branch access** (Users list columns: Name, Email, Branch, Role, Permissions, Tools).

## Document index

| # | Document | Covers (brief phases) |
|---|---|---|
| 01 | [Existing Application Analysis](./01-existing-application-analysis.md) | Phase 1 |
| 02 | [Feature Inventory](./02-feature-inventory.md) | Phase 2 |
| 03 | [Module & System Architecture](./03-module-and-system-architecture.md) | Phases 3–4 |
| 04 | [Database Architecture & ER Diagram](./04-database-architecture.md) | Phase 5 |
| 05 | [Multi-Branch Tenancy & RBAC](./05-multitenancy-and-rbac.md) | Phases 6–7 |
| 06 | [API Architecture](./06-api-architecture.md) | Phase 8 |
| 07 | [Frontend System Design](./07-frontend-system-design.md) | Phase 9 |
| 08 | [Security, Performance & Reporting](./08-security-performance-reporting.md) | Phases 10–12 |
| 09 | [Testing & Deployment](./09-testing-and-deployment.md) | Phase 13 |
| 10 | [Roadmap, Risks & Open Questions](./10-roadmap-risks-open-questions.md) | Phase 14 |

The final consolidated architecture document (Phase 15, all 22 sections) is the union of these files;
this README is its Executive Summary.

## Key architectural decisions at a glance

| Decision | Choice | Why |
|---|---|---|
| App style | Modular monolith (feature modules), REST API + React SPA | Preserve one deployable/one DB (matches the domain's tight cross-module joins: exam→student→class→fee); modules give domain boundaries without distributed-systems cost. |
| Module packaging | First-party `app/Modules/*` (PSR-4), **not** a heavy package | Zero framework lock-in, easy to reason about, no `nwidart` magic; can extract later. |
| Multi-branch | **Single DB, `branch_id` scoping** under an `organization_id`, enforced by a global scope + middleware | Shared reference data + cross-branch reporting are first-class; row-level isolation is simpler & cheaper than DB-per-tenant for this scale. |
| AuthZ | `spatie/laravel-permission` with **branch-scoped roles** (teams feature) + Policies | Matches observed role+granular-permission+branch model exactly. |
| Auth | **Sanctum** SPA cookie sessions (same-site) | First-party React SPA; no token storage in JS; CSRF-protected. |
| Frontend data | **TanStack Query** (server state) + minimal global store (auth/branch/session) | Server-state caching/invalidations dominate this app; avoids Redux bloat. |
| Forms | **React Hook Form + Zod**, schema shared with API contracts | Heavy form app; performant + typed validation. |
| Reporting | Queue-generated **PDF (server)** + client print; Excel/CSV via Laravel Excel | Marksheets/tabulation/admit cards are print-critical; large jobs must be async. |

See each document for the rationale behind every sub-decision (brief Rule 16).
