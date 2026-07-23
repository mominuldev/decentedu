# 10 — Roadmap, Risks & Open Questions (Phases 14–15 tail)

## Part A — Development roadmap (dependency-ordered)

Each phase is independently shippable and testable. Order follows real data dependencies discovered in
the analysis (academic core underpins everything; finance/exam sit on students+class_config).

### Phase 0 — Discovery & Architecture ✅ (this blueprint)
Analyze legacy app · finalize modules, DB, API contracts, RBAC · **approval gate** · legacy data-migration
plan (needs DB access).

### Phase 1 — Foundation
Laravel + React + Tailwind scaffold · module/service-provider framework · **Sanctum auth** (login, reset,
sessions) · base layouts + design-system primitives · API envelope + exception handling · **Users, Roles,
Permissions** (spatie, branch teams) · audit-log skeleton · CI/CD + test harness.
*Deliverable:* a user can log in, see an empty shell, permissions enforced.

### Phase 2 — Organizations & Branches
Organization/Branch models · `branch_user` membership · **Select Branch** + switch · `BranchContext`
middleware + global scope · branch settings · **isolation feature tests** (the safety backbone).
*Deliverable:* multi-branch context proven and tested.

### Phase 3 — Academic Foundation
Academic Year (session lifecycle) · Class/Shift/Section/Group/Category (SetupResource) · **Class Config**
+ **Group Config** · Subjects. *Deliverable:* the join backbone exists.

### Phase 4 — People Management
Students (identity + **enrollment history**) · bulk registration/update/photo/address · **migration**
(general/merit/push-back) · 9 student reports · HRM (designation, dept, employees, subject-teacher).
*Deliverable:* students & staff fully managed.

### Phase 5 — Routines & Attendance
Class routine + teacher routine · exam routine · **conflict detection** (new) · biometric attendance
(device map, time config, ingestion job, take/update, reports, absentee SMS hook).
*Deliverable:* scheduling + daily attendance.

### Phase 6 — Examinations & Results
Exam/subject/short-code/grade setup · exam/mark/final/4th-subject/class-teacher config · **mark input
grid** · **result processing (queued)** · marksheet/tabulation/merit/fail · admit card/seat plan/
attendance sheet. *Deliverable:* full assessment cycle (highest business complexity).

### Phase 7 — Finance
Student fees (heads/sub-heads/waivers/config/time) · dues + **fee collection** (receipts) ·
**double-entry accounting** (ledgers, vouchers, trial balance, income statement) · fees→GL posting.
*Deliverable:* billing + accounting with GL integrity.

### Phase 8 — Communications, Credentials & CMS
SMS (templates, phone book, sends, balance, delivery log) · Credentials (TC, testimonial, certificate,
ID cards, templates) · Website CMS (posts engine, menus, settings). *Deliverable:* comms + documents +
public site.

### Phase 9 — Reporting, Optimization & Hardening
Shared reporting/export subsystem finalized (PDF/Excel/print, queued) · caching · Horizon tuning · audit
coverage · security review · performance/load testing · legacy **data migration** + parity verification +
branch-by-branch cutover.

### Later (Future — doc 02 §D)
Guardian/student portal · online payments · mobile apps · library/inventory/transport/hostel · analytics.

**Sequencing rationale:** Auth→Branch→Academic must precede People; People precede Attendance/Exam/Fees;
Fees precede/parallels Accounting (posting); Reporting is cross-cutting and hardened last once data exists.

## Part B — Risks & key technical decisions

| # | Risk / Decision | Impact | Mitigation / Rationale |
|---|---|---|---|
| K1 | **Exam/result business rules** are the deepest, least-visible logic (GPA, 4th subject, merit types, final combination) | Wrong results = trust loss | Extract exact rules from legacy code/DB (needs access); unit-test each; parity harness vs legacy outputs |
| K2 | **Branch isolation correctness** | A leak is a serious breach | Triple-layer enforcement + dedicated isolation test suite as a release gate |
| K3 | **Legacy → new data migration** | Cutover risk, historical data | Importers validated against field inventory; per-branch cutover; dry-runs on staging; rollback plan |
| K4 | **Livewire→React rewrite** changes UX interaction model | User retraining, missed micro-behaviours | Preserve workflows; usability pass; keep legacy reference during build |
| K5 | **Bulk grids at scale** (marks/registration) | Perf + data-entry errors | Virtualized EditableGrid, batch save, optimistic UI, partial-failure reporting |
| K6 | **SMS is paid + external** | Cost, delivery failures | Balance guardrails, rate limits, delivery callbacks, retries, idempotency |
| K7 | **Accounting integrity** | Financial correctness | DB + application invariants (debit=credit), audit log, no hard deletes |
| K8 | **Scope creep** (12 modules) | Timeline | Phased, shippable increments; confirmed-only in v1; Future list parked |
| K9 | **Reporting fidelity** (marksheets/admit cards) | Print rejections | Server PDF + template config + visual diff tests |
| K10 | Modular-monolith discipline erodes over time | Coupling creep | Enforced module boundaries (deptrac/arch tests), events for cross-module effects |

**Decisions log (why):** modular monolith over microservices (relational, transactional domain);
single-DB `branch_id` over DB-per-tenant (cross-branch reporting + ops simplicity); Sanctum cookies over
JWT (SPA, no XSS token theft); spatie teams over bespoke RBAC (matches observed model); TanStack Query +
Zustand over Redux (server-state dominates); Query objects over generic repositories (avoid ceremony);
queued result/report processing (keep requests fast); server PDF (print fidelity). Each is expanded in its
section.

## Part C — Open questions (need your input before/early in build)

**Access & parity**
1. Can you provide **legacy source code and/or a DB dump**? It removes remaining `[inferred]` guesses
   (exact exam/GPA rules, statuses, fee/fine formulas) and enables an accurate data migration. *(Highest
   leverage item.)*
2. Confirm DB engine (assumed **MySQL**) and hosting constraints (shared vs VPS/cloud) — affects Redis/
   queue/object-storage availability.

**Product scope**
3. **Roles:** only *Super Admin* and *Admin* were observed. Do you want the proposed role set (Accountant,
   Exam Controller, Teacher, Admission Officer, Viewer — doc 05 B3), or keep it minimal + granular perms?
4. **Teacher/guardian portals** — in scope for v1 or Future? (Changes auth surface & API breadth.)
5. **Online payment** integration (bKash/Nagad/SSLCommerz) — v1 or Future?
6. Multi-**organization** SaaS (many independent orgs self-serve) vs single-operator managing all branches?
   Affects billing/onboarding, not the core schema.

**Domain specifics to confirm from evidence/code**
7. Exact **result algorithm**: merit-process types are now **confirmed** (Total-Mark / Grade-Point ×
   Sequential / Non-Sequential) and exam types are **confirmed** (Weekly, Monthly, Final, Grand Final).
   Still needed: how term marks are **weighted** when combining into Final/Grand Final, the exact
   **4th-subject GPA** treatment, and **tie-breaking** within a merit method.
8. **Fee/fine** rules: fine calculation (flat vs per-day), partial payments, waiver stacking.
9. **Attendance**: which biometric devices/protocol (ZKTeco?) for the ingestion adapter; period vs day.
10. Student **status lifecycle** transitions and re-admission rules.
11. Whether fee collection **must** auto-post to the GL (assumed yes) and the mapping.

**Non-functional**
12. Expected scale (branches, students/branch, peak concurrent users) to size infra.
13. Bilingual scope: is বাংলা required across **all** screens/reports, or admin-EN + reports-bilingual?
14. Data-retention/compliance requirements (audit retention, PII handling, backups).

---

## Approval gate

Per brief Rule 17: **this is the complete design; no implementation, migrations, or components have been
written.** On your review we will (a) resolve the open questions above, (b) adjust modules/DB/RBAC/roadmap
accordingly, then (c) begin **Phase 1 — Foundation**. Please confirm or annotate:
the module boundaries (doc 03), the DB backbone incl. `student_enrollments` (doc 04), the tenancy+RBAC
model (doc 05), and the roadmap sequencing (this doc).
