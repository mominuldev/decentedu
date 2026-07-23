# 06 — API Architecture (Phase 8)

REST, JSON, versioned under `/api/v1`. Auth via **Sanctum SPA cookies** (same-site) + XSRF header;
a Bearer-token guard is available for future mobile clients on the same routes.

## 1. Conventions

- **Resource URLs are plural nouns**, nested only one level where ownership is intrinsic.
- **Verbs:** `GET` (read), `POST` (create/actions), `PUT/PATCH` (update), `DELETE` (soft-delete).
- **Ambient context** (active branch, academic year) comes from the **session**, not the URL — so
  `/students` already means "this branch, current session". Overridable via `?academic_year_id=` for
  historical reads (permission-gated).
- Every request passes `auth:sanctum → EnsureBranchContext → permission:<x>`.
- **All list endpoints** support `?page`, `?per_page`, `?sort=-created_at`, `?search=`, and typed
  filters; responses paginate by default.

## 2. Standard response envelope

**Success**
```json
{
  "success": true,
  "message": "Students retrieved successfully",
  "data": [ /* resource or array */ ],
  "meta": {
    "pagination": { "total": 44, "per_page": 20, "current_page": 1, "last_page": 3 },
    "filters": { "class_config_id": 70 }
  }
}
```

**Single resource** → `data` is an object, `meta` omitted or `{}`.

**Error** (consistent shape for all failures)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": { "roll": ["The roll must be unique within this section."] },
  "error_code": "VALIDATION_ERROR"
}
```

| HTTP | error_code | When |
|---|---|---|
| 400 | BAD_REQUEST | malformed request |
| 401 | UNAUTHENTICATED | no/expired session |
| 403 | FORBIDDEN / NO_BRANCH_ACCESS | permission or cross-branch violation |
| 404 | NOT_FOUND | missing / out-of-branch (looks absent) |
| 409 | CONFLICT | business conflict (routine clash, duplicate roll) |
| 422 | VALIDATION_ERROR | FormRequest failure (`errors` map) |
| 423 | LOCKED | e.g. marks locked after result published |
| 429 | RATE_LIMITED | throttle |
| 500 | SERVER_ERROR | unhandled (no leak of internals) |

Implemented once via `Core\Http\ApiResponse` helper + an exception handler mapping Laravel exceptions to
this shape. Consistency is a first-class requirement (brief Phase 8).

## 3. Endpoint catalog (representative — one pattern, all modules follow)

### Auth & context
```
POST   /api/v1/auth/login                 {email,password,remember}     → user+branches
POST   /api/v1/auth/logout
GET    /api/v1/auth/me                                                   → user, active branch, perms
POST   /api/v1/auth/forgot-password       {email}
POST   /api/v1/auth/reset-password        {token,email,password}
GET    /api/v1/branches/mine                                             → branches user can access
POST   /api/v1/branch/switch              {branch_id}                    → active branch context
GET    /api/v1/academic-years  · POST /api/v1/academic-year/activate {id}
```

### Students (canonical CRUD + bulk + reports)
```
GET    /api/v1/students            ?search&class_config_id&group_id&category_id&status&sort&page
POST   /api/v1/students
GET    /api/v1/students/{student}
PUT    /api/v1/students/{student}
DELETE /api/v1/students/{student}
POST   /api/v1/students/bulk                 {academic_year_id,section_id,group_id,rows[]}  # grid create
PUT    /api/v1/students/bulk                 # multiple update
POST   /api/v1/students/bulk-photo           # multipart
POST   /api/v1/students/migrate              {type:general|merit, from, to, student_ids[]}
POST   /api/v1/students/migrate/push-back    {enrollment_ids[]}
GET    /api/v1/students/reports/{type}       ?filters  (class_wise|section_wise|group_wise|…)
GET    /api/v1/students/{student}/enrollments
```

### Academic setup (one shape reused for class/shift/section/group/category/…)
```
GET|POST            /api/v1/academic/{resource}
GET|PUT|DELETE      /api/v1/academic/{resource}/{id}
# resource ∈ classes, shifts, sections, groups, categories, class-configs, group-configs, subjects
```

### Examinations & results
```
GET|POST /api/v1/exam/exams · /exam/subjects · /exam/short-codes · /exam/grades
GET|POST|PUT /api/v1/exam/configs · /exam/mark-configs · /exam/final-mark-configs
GET    /api/v1/exam/marks        ?exam_id&subject_id&class_config_id&group_id
POST   /api/v1/exam/marks        {exam_id,subject_id,class_config_id,marks:[{student_id,is_absent,components:{}}]}
POST   /api/v1/exam/results/process   {type:general|merit|final, exam_id, class_config_id}   # queued job
GET    /api/v1/exam/results/tabulation ?exam_id&class_config_id           # returns data; ?format=pdf → job
POST   /api/v1/exam/admit-cards       {exam_id,class_config_id}           # queued PDF
```

### Fees & accounting
```
GET|POST /api/v1/fees/heads · /fees/sub-heads · /fees/configs · /fees/waivers
GET    /api/v1/fees/students/{student}/dues
POST   /api/v1/fees/collections     {student_id, items:[{sub_head_id, amount, fine}], paid_at, method}
GET    /api/v1/fees/reports/{type}
GET|POST /api/v1/accounting/ledgers
POST   /api/v1/accounting/vouchers  {type, date, note, entries:[{ledger_id,debit,credit}]}
GET    /api/v1/accounting/reports/{trial-balance|income-statement|voucher-list|journal}
```

### Attendance / Routines / Messaging / Credentials / CMS / Users
```
POST /api/v1/attendance/student/take     {class_config_id, date, period_id, entries[]}
POST /api/v1/attendance/ingest           {device_id, punches[]}           # device webhook, queued
GET|POST /api/v1/routines/class · /routines/exam
GET  /api/v1/routines/conflicts          ?class_config_id                 # NEW: conflict detection
POST /api/v1/messaging/send              {audience:class|section|contact|file, template_id, ...}
POST /api/v1/credentials/tc              {student_id, reason, left_date}   → pdf job
GET|POST /api/v1/cms/posts               ?type=notice
GET|POST /api/v1/users · /roles · /permissions
```

## 4. Filtering, sorting, pagination (uniform)

- **Filtering:** whitelisted params per endpoint, resolved by the module's Query object (never raw
  user SQL). Supports `field=value`, `field__in=`, date ranges `created_between=a,b`.
- **Search:** `?search=` runs a scoped LIKE/fulltext over declared searchable columns.
- **Sorting:** `?sort=field` / `-field` (desc), whitelisted; default per endpoint.
- **Pagination:** length-aware, `per_page` capped (e.g. ≤200); `meta.pagination` always present on lists.
- **Sparse fields / includes:** `?include=enrollments,guardian` maps to eager-loads (prevents N+1).

## 5. Validation

- Every write endpoint has a **FormRequest**: type rules, existence + **branch-ownership** rules
  (a `class_config_id` must belong to the active branch), business rules (roll unique per section-session;
  `mark ≤ total_marks`; voucher debit = credit; `is_absent` xor marks).
- Validation errors → `422` with field-keyed `errors` (React RHF maps these straight onto fields).

## 6. Idempotency, concurrency & rate limits

- **Idempotency-Key** header honoured on `results/process`, `fees/collections`, `messaging/send`.
- **Optimistic locking** (`updated_at`/version) on marks & configs to avoid lost updates in shared grids.
- **Rate limiting:** login (per IP+email), SMS send, report/PDF generation, and a global per-user quota.

## 7. Contract & docs

- OpenAPI 3 spec generated from routes + FormRequests + Resources (Scribe/L5-Swagger) → published docs
  and used to **generate TS types** for the frontend (single source of truth, doc 07).
- Versioning: additive changes in `v1`; breaking changes → `v2` namespace, old kept during migration.
