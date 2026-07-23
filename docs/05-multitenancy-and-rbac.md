# 05 — Multi-Branch Tenancy & RBAC (Phases 6–7)

## Part A — Multi-branch strategy

### A1. Chosen model: single DB, `organization → branch`, row-level `branch_id` scoping

**Decision:** one database; a two-level hierarchy **Organization → Branch**; every business row carries
`branch_id`; isolation enforced by a global Eloquent scope + request middleware; **shared global data**
(none currently — all masters are per-branch) modelled explicitly if it ever arises.

**Why this over the alternatives:**

| Option | Verdict | Reason |
|---|---|---|
| DB-per-tenant / schema-per-tenant | ✗ | Hundreds of small branches → operational nightmare (migrations × N, connections), and it *blocks* the confirmed need for **cross-branch reporting** by a group admin. |
| Full tenancy package (stancl/tenancy) | ✗ | Over-engineered for row-scoping; adds context-switching complexity we don't need. |
| **Single DB + `branch_id` scope** | ✓ | Simple, cheap, cross-branch queries trivial, shared reference tables possible, matches the observed "Select Branch" + one-account-many-branches UX exactly. |

Evidence this matches reality: one login sees **5 branches**; the **Select Branch** screen sets an
active branch; the Users list assigns each user a **Branch**; the dashboard/session header is
branch-scoped.

### A2. Entity ownership

| Level | Entities |
|---|---|
| **Organization** (shared across its branches) | organization profile, billing/subscription, org-level Super Admin users |
| **Branch** (isolated) | **everything academic/operational** — academic years, classes/sections/configs, students, employees, attendance, exams, marks, results, fees, ledger, SMS, posts, settings |
| **Global (platform)** | permission catalog definitions, currencies, country/religion enums, system roles templates |
| **Shared-optional (future)** | a group could share a grade scale or fee template across branches — modelled as `branch_id NULL = org-default` with per-branch override (not needed today; noted for extension) |

### A3. How a user accesses branches

```
users ──< branch_user >── branches         (many-to-many; a user can belong to N branches)
   branch_user: (user_id, branch_id, role_id, is_default)
```

- A user has **one role per branch** (Super Admin might span all branches of the org; a Teacher belongs
  to one). This is exactly `spatie/permission`'s **teams** feature with `team_id = branch_id`.
- On login the API returns the user's branches. If >1 and none pinned → client shows **Select Branch**;
  the choice sets the **active branch** (stored server-side in the session + echoed as a claim).

### A4. Branch switching

- Endpoint `POST /api/v1/branch/switch {branch_id}` → validates membership → sets active branch in the
  Sanctum session → returns branch profile + that branch's permission set + current academic year.
- Client clears TanStack Query cache for branch-scoped keys on switch (queryKey includes `branchId`, so
  caches are naturally partitioned — see doc 07).

### A5. Enforcement (how cross-branch access is *prevented*)

Three layers (defence in depth):

1. **Middleware `EnsureBranchContext`** resolves the active branch from the session and asserts the user
   is a member; rejects otherwise (`403`). Sets `app(BranchContext::class)->id`.
2. **Global scope `BelongsToBranch`** on every tenant model auto-adds `WHERE branch_id = :active` to
   *every* query, and auto-fills `branch_id` on create. A developer literally cannot forget it. Bypass
   only via an explicit `->withoutBranchScope()` used solely in org-level cross-branch report services,
   themselves permission-gated (`report.cross_branch`).
3. **Route-model binding + Policy** double-check the resolved model's `branch_id` matches context before
   any action (guards against IDOR even if a scope is bypassed).

### A6. Cross-branch reporting

- Org-level roles with `report.cross_branch` can query an **aggregation service** that runs
  `withoutBranchScope()` and explicitly filters to the set of branches the user may see
  (`branch_user`), never "all rows".
- Results are grouped by branch; row-level data of a branch is never returned to a user lacking that
  branch membership.

### A7. Real-world access scenarios

| Actor | Scenario | Outcome |
|---|---|---|
| Group Super Admin (org) | opens Students list | must pick a branch; sees only that branch's students; can run a **cross-branch enrolment summary** across their branches |
| Branch Admin (Demo School) | opens Fee Collection | scope pins `branch_id = Demo School`; API for a Demo College student → `403/404` |
| Teacher (one branch) | tries `GET /students/{id}` of another branch by guessing id | global scope + policy → `404` (row invisible) |
| Accountant | posts a journal voucher | voucher + entries stamped with active branch; trial balance is branch-scoped |
| User with 1 branch | logs in | `is_default` branch auto-selected, no Select-Branch screen |

## Part B — Authorization (RBAC)

### B1. Model

```
User ──< branch_user (role per branch) >── Branch
Role ──< role_has_permissions >── Permission
User ──< model_has_permissions >── Permission        (per-user grant/deny override = the "Tools" column)
```

**Decision:** `spatie/laravel-permission` with the **teams** feature (`team_id = branch_id`) for
branch-scoped roles, **plus** direct per-user permissions for the granular overrides the current app
exposes ("Permissions"/"Tools" columns). Policies wrap models for object-level checks.

**Why:** the crawl shows precisely role **+** per-user granular permission **+** branch — spatie models
all three with mature, cached checks. Building bespoke RBAC would reinvent it with more risk.

### B2. Permission catalog

Permissions are `module.resource.action`, generated from the module map so the menu, API, and UI share
one source of truth. Examples:

```
students.student.view|create|update|delete|export
students.migration.run
exam.marks.input|update|delete
exam.result.process|publish
fees.collection.create|remove
accounting.voucher.create
attendance.student.take|update
users.manage   roles.manage   branch.switch
report.cross_branch
```

`view/manage` split, plus dangerous actions (`result.publish`, `fees.remove`, `marks.delete`) as
discrete permissions so they can be withheld even from an "Admin".

### B3. Roles (confirmed vs proposed — clearly separated per brief Rule 7)

**Confirmed present in the live app:** `Super Admin`, `Admin`.

**Proposed additional roles (NOT yet confirmed — require your approval):**

| Role | Scope | Purpose |
|---|---|---|
| Super Administrator *(confirmed)* | platform/org | full access incl. cross-branch, user & role management |
| Organization Administrator *(proposed)* | org | manage all branches of one org, cross-branch reports, no platform config |
| Branch Administrator *(≈ confirmed "Admin")* | branch | full access within one branch |
| Accountant *(proposed)* | branch | fees + accounting + related reports only |
| Exam Controller *(proposed)* | branch | exam setup/config/marks/results only |
| Teacher *(proposed)* | branch | mark entry for assigned subjects, class-teacher views, routines |
| Front Desk / Admission Officer *(proposed)* | branch | admission + student registration + certificates |
| Viewer / Auditor *(proposed)* | branch/org | read-only + reports |

> Proposed roles are a **starting RBAC matrix** to validate with you; the engine supports any custom role
> since permissions are granular. We will not seed proposed roles without sign-off.

### B4. How the key roles behave

- **Super Administrator:** bypasses permission checks via a Gate `before` hook (`isSuperAdmin`), still
  subject to branch context for data views, can impersonate/manage users, define roles/permissions.
- **Branch Administrator ("Admin"):** all permissions **within their branch**; cannot see other branches
  unless separately granted; cannot manage platform-level settings.
- **Teacher (proposed):** `exam.marks.input` limited further by **subject-teacher assignment** — a
  policy checks the teacher is assigned to that `class_config × subject` (object-level rule beyond the
  coarse permission).
- **Accountant (proposed):** fees + accounting permissions; no academic/exam access.

### B5. Enforcement points

- **API:** `permission:` middleware on routes + `$this->authorize()` (Policy) in controllers/actions for
  object-level checks (e.g. teacher↔subject, branch ownership).
- **UI:** `usePermission('exam.marks.input')` gates menu items, buttons, and routes (`PermissionRoute`).
  UI gating is UX only — the API is the real boundary (never trust the client).
- **Caching:** permission maps cached in Redis per `(user, branch)`; invalidated on role/permission change.

### B6. Session, branch & permission claims

On login/switch the API returns:
```json
{ "user": {...}, "active_branch": {...}, "academic_year": {...},
  "branches": [...], "role": "Admin", "permissions": ["students.student.view", ...] }
```
The React session store holds this; all permission UI checks are local + instant, re-synced on switch.
