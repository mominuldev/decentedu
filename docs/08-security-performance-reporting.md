# 08 — Security, Performance & Reporting (Phases 10–12)

## Part A — Security architecture (Phase 10)

| Area | Control | Notes |
|---|---|---|
| **Authentication** | Sanctum SPA cookie sessions (HttpOnly, Secure, SameSite=Lax); bcrypt/argon2 hashing; "remember me" | no JWT-in-JS → no XSS token theft |
| **Password policy** | min length, breach check (HaveIBeenPwned k-anon), configurable complexity, forced reset on admin-create | |
| **Login protection** | rate-limit per IP+email, exponential backoff, lockout + captcha after N fails, optional 2FA (TOTP) for admins | |
| **Password reset** | signed, single-use, short-TTL token via email; no user enumeration (uniform response) | |
| **Session security** | rotate on login, invalidate on logout/password-change, idle + absolute timeout, device/session list | |
| **CSRF** | Sanctum XSRF-TOKEN cookie + `X-XSRF-TOKEN` header on all mutating requests | |
| **API security** | all routes behind `auth:sanctum`; per-route `permission:`; throttling; no mass-assignment (`$fillable`/FormRequests) | |
| **Authorization** | Policies (object-level) + spatie permissions (action-level) + branch scope (row-level) | doc 05 |
| **Branch data isolation** | global scope + middleware + policy triple-check; `withoutBranchScope` only in gated cross-branch services | **prevents cross-branch access** |
| **Input validation** | FormRequests on every write; whitelist filters; reject unknown params | |
| **SQL injection** | Eloquent/parameter binding only; no raw string SQL with user input; Query objects whitelist columns | |
| **XSS** | React auto-escapes; sanitize CMS/rich-text (HTMLPurifier) on store & render; CSP headers | CMS `posts.body` is the risk surface |
| **File uploads** | type + size + magic-byte validation; store outside webroot (S3/private disk); randomized names; image re-encode; AV scan hook | photos, imports, ID templates, post images |
| **Rate limiting** | login, SMS send, report generation, bulk imports, global per-user | protects paid SMS + heavy jobs |
| **Audit logging** | `AuditLog` records actor, branch, action, before/after on sensitive models (marks, fees, vouchers, users, permissions) | dispute resolution + compliance |
| **Sensitive data** | encrypt secrets (SMS gateway keys, tokens) via Laravel encryption; PII access logged; least-privilege DB user | |
| **Transport** | HTTPS enforced (HSTS), secure headers (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy) | |
| **Dependencies** | Dependabot/`composer audit`/`npm audit` in CI | |

### How cross-branch access is prevented (explicit, per brief)
1. Active branch is server-side session state, asserted against `branch_user` membership by middleware.
2. Global scope injects `branch_id = active` into every query and stamps it on writes.
3. Route-model binding + Policy verify the resolved record's `branch_id` equals context (blocks IDOR).
4. Cross-branch reads require `report.cross_branch` and go through a service that filters to the user's
   permitted branch set — never "all rows". A user guessing another branch's record id gets `404`.

## Part B — Performance & scalability (Phase 11)

**Design targets:** hundreds of branches, tens of thousands of students, exam batches of thousands of
mark rows, large printable reports.

| Lever | Strategy |
|---|---|
| **DB indexing** | `branch_id`-led composite indexes matching dominant filters; unique business keys; covering indexes for report queries; fulltext for student search (doc 04 §6) |
| **Query optimization** | Query objects + `->select()` needed columns; avoid `SELECT *` on wide tables; paginate always |
| **Eager loading** | `?include=` → whitelisted `with()`; N+1 guard (`Model::preventLazyLoading` in non-prod) |
| **Pagination** | length-aware default; cursor pagination for very large exports |
| **Caching (Redis)** | permission maps per (user,branch); branch settings; reference lists; dashboard aggregates (short TTL); grade scales. Tag-based invalidation on writes |
| **Queues (Redis + Horizon)** | result processing, PDF/report generation, bulk import, SMS send, attendance device ingestion, migration — all async with progress + notification on completion |
| **Background processing** | long jobs report progress via a `jobs`/status endpoint the UI polls; user is emailed/notified when done |
| **Large exam datasets** | chunked reads/writes; process results per class_config; store computed `results`/`positions` (not recomputed on read) |
| **Report generation** | queued; artifact stored on disk/S3; user downloads when ready; cached by (params hash) |
| **File storage** | object storage (S3-compatible) for photos/docs/reports; CDN for public + static |
| **API performance** | HTTP caching for reference lists (ETag); gzip/brotli; DB read-replica option for reports |
| **Frontend** | code splitting, virtualization, query caching (doc 07 §10) |

**When to add infra (only with reason — brief Rule 9):**
- **Redis** from day one (cache + queue + session) — clear need (permissions, heavy jobs).
- **Horizon** with queues — required for async result/report/SMS.
- **Read replica** only when report load measurably impacts OLTP.
- **Full-text/Meilisearch** only if MySQL fulltext proves insufficient for student search at scale.
- **Object storage** when file volume/backup makes local disk impractical (early for multi-branch).

## Part C — Reporting & export system (Phase 12)

Reporting is a **shared `Core\Reporting` subsystem** because print/PDF is mission-critical and identical
in shape across modules (student lists, marksheets, tabulation, admit cards, fee dues, trial balance).

### Architecture
```
ReportRequest (type, params, format) ─▶ Authorize (permission + branch)
   ─▶ ReportDefinition (query + view/template + columns)
      ─▶ small/interactive  → render inline JSON (DataTable) / on-the-fly PDF
      ─▶ large/batch        → dispatch GenerateReport job → store artifact → notify → download link
```

- **Report definitions** are classes (`MarksheetReport`, `TabulationReport`, `FeeDueReport`, …) declaring
  their query, permission, filters, and output template — consistent + testable.
- **PDF:** server-side (`Spatie Browsershot`/`dompdf`) for pixel-accurate marksheets, admit cards, TC, ID
  cards (template-driven), tabulation. Signature & logo from branch/signature config.
- **Excel/CSV:** Laravel Excel (`maatwebsite/excel`) for data exports (student lists, dues, ledgers) and
  **typed import templates** with validation preview (brief R6).
- **Print layouts:** dedicated `/print/*` React routes + print CSS for browser printing where a live view
  suffices (routines, quick lists).
- **Large reports:** always queued; progress + notification; artifact cached by params hash; retention policy.
- **Permission checks:** every report authorizes on its permission and is branch-scoped; cross-branch
  reports need `report.cross_branch`.
- **Filters:** standard cascading filters (year/class/section/group/exam) reused via shared filter DTOs.
