# 03 — Module & System Architecture (Phases 3–4)

## 1. Architecture style — modular monolith

**Decision:** one Laravel application, one MySQL database, split into **feature modules** with clear
domain boundaries, exposing a **versioned REST API** consumed by a **React SPA**.

**Why (not microservices):** the domain is densely relational — a marksheet touches student, enrolment,
class-config, subject, grade scale, exam config and branch; fee collection posts to the general ledger.
These are transactional, same-request joins. Splitting into services would force distributed transactions
and chatty calls for no scaling benefit at this size (hundreds of branches, tens of thousands of students).
A modular monolith keeps **one deploy, one DB, ACID transactions**, while modules give us **domain
boundaries, testability, and a clean extraction path** if any module (e.g. Messaging, Accounting) ever
needs to be a service later.

## 2. Backend layout

```
app/
├── Core/                        # cross-cutting framework glue (no business rules)
│   ├── Http/                    # base Controller, ApiResponse, middleware
│   ├── Concerns/                # BelongsToBranch, HasSerial, HasStatus traits
│   ├── Tenancy/                 # BranchContext, ScopeByBranch global scope
│   ├── Support/                 # helpers, enums base
│   └── Reporting/               # PDF/Excel engine (shared)
└── Modules/
    ├── Auth/            Users/            AccessControl/     (identity + RBAC)
    ├── Organizations/  Branches/         Settings/          (tenancy)
    ├── Academic/        Admissions/       Students/          (academic core + people)
    ├── Hr/              Attendance/                          (staff + attendance)
    ├── Routines/        Examinations/     Results/           (assessment)
    ├── Fees/            Accounting/                          (finance)
    ├── Messaging/       Credentials/      Cms/               (comms/docs/website)
    └── AuditLog/                                             (shared observability)
```

### 2.1 Internal structure of every module (uniform)

```
Modules/Students/
├── Domain/
│   ├── Models/            Student.php, Enrollment.php …        # Eloquent + relations + scopes
│   ├── Enums/             StudentStatus.php, Gender.php
│   ├── Events/            StudentRegistered.php, StudentMigrated.php
│   └── Exceptions/
├── Application/
│   ├── Actions/           RegisterStudentsBulk.php, MigrateStudents.php …  # use-cases (1 public method)
│   ├── Services/          EnrollmentService.php               # multi-step orchestration
│   ├── Queries/           StudentListQuery.php                # read models / filters
│   └── DTOs/
├── Http/
│   ├── Controllers/Api/V1/   StudentController.php …
│   ├── Requests/          RegisterStudentsRequest.php …       # FormRequest validation
│   ├── Resources/         StudentResource.php, StudentCollection.php
│   └── Policies/          StudentPolicy.php
├── Database/
│   ├── Migrations/        Factories/     Seeders/
├── Jobs/                  Listeners/     Notifications/
├── Routes/                api.php
├── Providers/             StudentsServiceProvider.php
└── Tests/                 Unit/  Feature/
```

**Why Actions + Services + Queries:**
- **Actions** = single business use-case, invoked from controllers, jobs, console, tests. Keeps
  controllers thin and logic reusable (e.g. bulk registration runs from API *and* from CSV import job).
- **Services** = orchestration spanning entities/modules within a transaction (result processing).
- **Queries** = encapsulated read/filter/sort/paginate builders for list endpoints (consistent filtering).
- **No generic repository layer** over Eloquent — Eloquent already is the data layer; repositories would
  be ceremony. We use **Query objects** only where list filtering is non-trivial (brief "repository usage
  strategy": *use query objects, not blanket repositories*).

### 2.2 Module boundaries & communication

- Modules talk **inward via Actions/Services** and **outward via domain Events**, never by reaching into
  another module's controllers.
- **Allowed coupling:** a module may depend on models/DTOs of a more-foundational module (Students may
  read `Academic\ClassConfig`), but not vice-versa. Dependency direction: `Academic → Students →
  {Attendance, Examinations, Fees}`; finance/messaging are leaf consumers.
- **Cross-module side-effects use events**: `FeeCollected` → `Accounting` listener posts a receive
  voucher; `StudentMarkedAbsent` → `Messaging` listener queues guardian SMS; `ResultPublished` →
  `Messaging`. This keeps Fees ignorant of Accounting internals and enables async.

### 2.3 Service providers & auto-discovery

Each module has a `ServiceProvider` that registers its routes (`Routes/api.php` prefixed
`/api/v1/<module>`), migrations, policies, events, and translations. A `ModuleServiceProvider` in `Core`
discovers and boots them (array in `config/modules.php` — explicit, no filesystem magic → predictable).

## 3. Backend cross-cutting design (Phase 4 · Backend)

| Concern | Approach | Why |
|---|---|---|
| **Domain services** | `Application/Services/*` per module, transactional | orchestration in one testable place |
| **Action classes** | one use-case per class, `handle()`/`__invoke()` | reuse across HTTP/queue/CLI |
| **Repository strategy** | Eloquent + **Query objects** for lists; no generic repos | avoid ceremony, keep Eloquent power |
| **Authorization** | Policies per model + `spatie/permission` gates, branch-scoped | matches observed RBAC (doc 05) |
| **Validation** | FormRequests; shared rule objects (roll-unique, branch-owned-fk) | server is source of truth |
| **API Resources** | `JsonResource` per model; consistent envelope | stable contract for React |
| **Events/Listeners** | domain events; listeners queued for side-effects | decoupling + async |
| **Jobs/Queues** | Redis queue: result processing, report PDF, bulk import, SMS send, attendance ingest | keep requests fast |
| **Notifications** | Laravel Notifications (SMS channel + database + mail) | unified fan-out |
| **Caching** | Redis: permission maps, branch settings, reference lists, dashboard aggregates | hot, rarely-changing data |
| **Logging** | structured JSON logs (stderr), context = user/branch/session/request-id | traceability |
| **Audit trail** | `AuditLog` module via model observers on sensitive models | marks/fees/users disputes |
| **Transactions** | Actions wrap multi-write ops in `DB::transaction`; DB constraints as backstop | integrity |
| **Idempotency** | idempotency keys on result-process & fee-collect endpoints | safe retries |

## 4. Frontend architecture (Phase 4 · Frontend)

**Decision:** React (Vite) SPA, TypeScript, Tailwind, feature-first structure, TanStack Query for server
state, React Router for routing, React Hook Form + Zod for forms, a thin Zustand store for session
(auth user, active branch, active academic session, permissions).

```
resources/js/
├── app/                 # bootstrap, providers, query client, router, error boundary
├── layouts/             # AuthLayout, DashboardLayout (sidebar+topbar), PrintLayout
├── components/          # design-system primitives (Button, Table, Modal, Drawer, Form fields…)
├── features/
│   ├── auth/            branches/         academic/         admissions/
│   ├── students/        hr/               attendance/       routines/
│   ├── examinations/    results/          fees/             accounting/
│   ├── messaging/       credentials/      cms/              settings/  users/
│   └── dashboard/
│       └── (each: api/  components/  hooks/  routes.tsx  schemas.ts  types.ts)
├── services/            # apiClient (axios), sanctum, queryKeys, errors
├── hooks/               # useAuth, useBranch, useSession, usePermission, usePaginatedQuery
├── lib/                 # formatters, i18n (en/bn), pdf/print helpers, cn()
├── stores/              # session store (zustand)
├── types/               # shared API types (generated from backend where possible)
└── routes/              # route table, ProtectedRoute, PermissionRoute
```

**Why feature-first:** mirrors backend modules 1:1 → a developer works one vertical slice
(`features/students` ↔ `Modules/Students`) end-to-end. Shared UI lives in `components/`, shared logic in
`hooks/`/`lib/`. Detailed UI system in **doc 07**.

**Why TanStack Query + small Zustand (not Redux):** ~95% of state is server data (lists, configs) whose
real needs are caching, background refetch, and invalidation on mutation — exactly Query's job. The only
true global client state is *who am I / which branch / which session / what can I do*, which is tiny →
Zustand. This avoids Redux boilerplate (brief "global state strategy": minimal).

## 5. Request lifecycle (end-to-end example: bulk mark input)

```
React MarkInputGrid
  └─ POST /api/v1/exam/marks  (Sanctum cookie + XSRF)  { exam_id, subject_id, class_config_id, marks[] }
       └─ Middleware: auth:sanctum → resolve BranchContext → EnsureBranchAccess → permission:exam.marks.input
            └─ StoreMarksRequest (validate ranges vs MarkConfig, roll ownership, is_absent xor marks)
                 └─ SaveSubjectMarks Action (DB::transaction: upsert marks, recompute subject aggregate)
                      └─ event MarksSaved → (queued) invalidate result cache for class_config
                           └─ MarksResource → { success, message, data, meta }
```

## 6. Why this satisfies the brief's non-functional goals

- **Maintainability:** uniform module + feature-slice structure; one CRUD pattern for ~30 setup screens.
- **Security:** branch scoping + policies enforced centrally (doc 05, 08).
- **Performance:** queue heavy work, cache hot reads, index the join backbone (doc 08).
- **Scalability:** stateless API behind a load balancer; Redis for cache/queue/session; modules
  extractable if ever needed.
