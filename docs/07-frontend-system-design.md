# 07 — Frontend System Design (Phase 9)

React 19 + TypeScript + Vite + Tailwind CSS. Modern, clean, fast, responsive, accessible, consistent —
purpose-built for dense school-admin workflows (bulk grids, filters, print). The existing business
workflows are preserved; the UI is redesigned (not copied — brief Rule, Phase 9).

## 1. Tech choices (with rationale)

| Concern | Choice | Why |
|---|---|---|
| Build | Vite | fast HMR, first-class TS, Laravel Vite plugin |
| Language | TypeScript (strict) | large form-heavy app; types from OpenAPI |
| Styling | Tailwind + CSS variables for theme tokens | consistent design system, dark-mode ready, no CSS sprawl |
| Components | Headless UI + Radix primitives, styled with Tailwind (own DS wrapper) | accessible-by-default modals/menus/tabs; we own the look |
| Server state | **TanStack Query** | caching, background refetch, mutation invalidation |
| Client state | **Zustand** (session slice only) | tiny global state: user/branch/session/permissions |
| Forms | **React Hook Form + Zod** | performant large forms, typed validation shared with API |
| Routing | React Router v6 (data routers) | nested layouts, loaders, protected routes |
| Tables | TanStack Table + virtualization (TanStack Virtual) | huge student/mark grids stay fast |
| i18n | i18next (en/bn) | confirmed bilingual requirement |
| HTTP | Axios instance w/ XSRF + 401/refresh handling | Sanctum cookie flow |
| Dates | Day.js | light, locale-aware (bn) |
| Charts | Recharts | dashboard analytics |
| PDF/print | server-generated PDFs + a dedicated PrintLayout for browser print | marksheets/admit cards fidelity |
| Toasts | Sonner (or Radix Toast) | non-blocking feedback |

## 2. Application structure

(See doc 03 §4 for the tree.) Each `features/<x>/` slice = `api/` (query/mutation hooks), `components/`,
`schemas.ts` (Zod), `types.ts`, `routes.tsx`. Cross-feature UI lives in `components/` (the design system);
cross-feature logic in `hooks/`/`lib/`.

## 3. Routing & layouts

```
/login, /forgot-password, /reset-password        → AuthLayout
/select-branch                                    → minimal layout (when >1 branch, none default)
/*                                                → DashboardLayout (sidebar + topbar + breadcrumbs)
   /                      dashboard
   /students, /students/:id, /students/bulk …     feature routes
   /exam/marks, /exam/results/tabulation …
   /print/*                                       → PrintLayout (no chrome, print CSS)
```

- **`ProtectedRoute`** — requires auth; redirects to `/login` preserving `returnTo`.
- **`BranchRoute`** — requires an active branch; else `/select-branch`.
- **`PermissionRoute permission="exam.marks.input"`** — else renders a 403 page; also hides the nav item.
- Route-level **code splitting** (`React.lazy`) per feature → small initial bundle.

## 4. Authentication flow

```
1. GET /sanctum/csrf-cookie
2. POST /auth/login  (cookie set)
3. GET /auth/me → hydrate session store {user, branches, active_branch, academic_year, permissions}
4. branches.length>1 && !default → /select-branch → POST /branch/switch → refresh /auth/me
5. Axios 401 interceptor → clear store → /login
```

Permissions live in the store → `usePermission()` is synchronous; menu, buttons, and routes read it.

## 5. Server-state strategy (TanStack Query)

- **Query keys** are branch-partitioned: `['students', branchId, academicYearId, filters]`. Switching
  branch changes the key → no stale cross-branch data; on switch we also `queryClient.clear()` scoped keys.
- **Mutations** invalidate related keys (create student → invalidate `['students', …]` and dashboard).
- **Optimistic updates** for grid edits (mark input, fee items) with rollback on error.
- **`usePaginatedQuery`, `useResourceList`, `useSetupResource`** hooks standardise list/CRUD wiring.

## 6. Design system (component inventory)

**Primitives:** Button, IconButton, Input, Textarea, Select (async, Select2-parity via Radix+virtual),
MultiSelect, Combobox, Checkbox/Radio/Switch, DatePicker/TimePicker (bn locale), FileUpload/ImageCropper,
Badge/Chip, Avatar, Tooltip, Tabs, Accordion, Card, Stat/KpiCard, Breadcrumbs, Pagination, Alert.

**Composites:**
- **DataTable** — column defs, server sort/filter/paginate, row selection, **bulk action bar**, sticky
  header, column visibility, density, CSV export, empty/loading/error slots, virtualization for large sets.
- **EditableGrid** — the bulk-entry workhorse (registration, mark input, fee config): keyboard
  navigation, paste-from-Excel, per-cell validation, dirty-row tracking, batch save.
- **FilterBar** — cascading selects (Academic Year → Class → Section → Group, mirroring
  `get_group_by_section`), chips for active filters, saved views.
- **ResourceForm** — schema-driven form (Zod) with server-error mapping, autosave-draft optional.
- **SetupResource** — one component renders all ~30 reference-data CRUD screens
  (`name/name_bn/serial/status`) from a config → massive consistency + code reduction (brief R7).
- **Modal / Drawer / ConfirmDialog** — forms in drawers, confirmations for destructive/dangerous actions
  (bulk delete, mark delete, result publish).
- **Toast** notifications; **PrintPreview** wrapper.

## 7. State per interaction (UX states everywhere)

Every data surface implements the full set (brief Phase 9):
- **Loading:** skeletons for tables/cards (not spinners) → perceived speed.
- **Empty:** illustration + primary CTA (e.g. "No students yet — Register students").
- **Error:** inline ret[ry] card with request id; never a blank screen.
- **Success:** toast + optimistic UI.
- **Partial (bulk):** per-row success/error summary after batch save (import/registration/marks).

## 8. Dashboard (redesigned, superset of the current 4 cards)

KPI row (Total/Present/Absent students, SMS balance) **plus** Today's Collection, Outstanding Dues,
Exam-in-progress, Recent Notices; attendance trend chart; quick actions gated by permission. All widgets
are branch+session scoped and lazy-loaded.

## 9. Accessibility & responsiveness

- Radix/Headless UI → focus management, ARIA, keyboard nav out of the box.
- Sidebar collapses to a drawer on mobile; DataTables switch to card/stacked layout < md.
- Color-contrast AA; visible focus rings; forms fully keyboard-operable; print styles for all reports.
- i18n: EN/বাংলা toggle in topbar; number/date localisation; RTL-safe utilities (future-proof).

## 10. Performance (frontend)

- Route + feature code-splitting; prefetch on nav hover.
- Virtualized grids; server-side pagination default; debounced search.
- Query caching + `staleTime` tuned per resource (reference data long, live counts short).
- Image optimization for student photos/ID cards; CDN for static assets.

## 11. Type safety end-to-end

OpenAPI (doc 06 §7) → generated `types/api.d.ts`; Zod schemas in `features/*/schemas.ts` validate forms
and are kept in lockstep with FormRequests. One contract, both ends.
