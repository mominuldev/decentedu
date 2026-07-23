# 02 — Feature Inventory (Phase 2)

Four strictly-separated categories (brief Phase 2). **Existing** = confirmed in the crawl.
**Implied** = business logic that must exist for Existing to work, but has no dedicated screen.
**Recommended** = improvements we advise. **Future** = later-phase ideas. Nothing is mixed.

Legend: ✅ confirmed screen · 🟡 confirmed but demo-errored (feature exists, data missing) · 🔧 inferred logic.

---

## A. EXISTING FEATURES (confirmed in the live app)

### Core Platform
- ✅ Email/password authentication, "keep me signed in", forgot-password flow, logout.
- ✅ Users management (list/create/edit) with Name, Email, **Branch**, **Role**, **Permissions**, **Tools**.
- ✅ Roles: **Super Admin**, **Admin**; **per-user granular permissions**.
- ✅ Own profile + edit profile / change password.
- ✅ Basic/branch settings.
- 🔧 SMS balance metering (paid resource) surfaced on dashboard.

### Organization & Branch
- ✅ **Multiple branches per account** (5 in demo).
- ✅ **Select Branch** screen; active branch frames all data.
- ✅ Per-user branch assignment.
- 🔧 Branch-level data isolation across every module.

### Academic
- ✅ Academic Year (session) setup.
- ✅ Class, Shift, Section, Group, Category reference data (bilingual, serial-ordered, status).
- ✅ **Class Config** (class×shift×section) and **Group Config** (class×group) pivots.

### Admission
- ✅ Admission Year, Admission Class, Quota, Quota Config (🟡 demo-errored setup).
- ✅ Application List; 🔧 applicant → student conversion, quota-based selection.

### Student
- ✅ Bulk registration (grid), single create (🟡), bulk delete.
- ✅ Bulk updates: multiple edit, photo, class info, address.
- ✅ Migration: General / Merit / Push Back (year-end promotion + reversal).
- ✅ 9 student reports (class/section/group/category/gender/religion wise, section summary, profile, summary).
- ✅ Student list.

### HRM
- ✅ Designation, HRM Section (department).
- ✅ Employee single create (fields confirmed), multiple create (🟡), updates.
- ✅ Subject-Teacher assignment.
- ✅ Employee list.

### Attendance (biometric)
- ✅ Staff & Student sub-systems.
- ✅ Time Config, **Device ID Map**, Take Period, Report Generate.
- ✅ Take/Update student attendance; daily reports (3 staff + student variants).
- ✅ Absentee **SMS template** integration.

### Routine
- ✅ Exam Routine create/list (date/time/room/session per subject).
- ✅ Class Routine + Teacher Routine print.

### Exam & Results
- ✅ Exam, Subject, Short Code, Grade setup.
- ✅ Exam/Subject/Mark/Final-Mark config, Fourth Subject, Admit Instruction, Signature, Class Teacher config.
- ✅ Mark Input/Update/Delete (grid with is_absent + component marks).
- ✅ Result process: General/Merit/Final.
- ✅ Results: Merit CW/SW, Fail CW/SW, Marksheet, Marksheet Final, Tabulation, Short Tabulation.
- ✅ Admit Card, Seat Plan, Attendance Sheet.

### Accounts
- ✅ **Student fees:** Head/Sub Head/Waiver setup, Fee/Waiver/Time config, Collect/Remove fee (🟡 collection), due & paid reports.
- ✅ **General accounting (double-entry):** Ledger (🟡), Receive/Payment/Contra/Journal vouchers, Trial Balance, Income Statement, Voucher List, Journal Report, Cash Summary.

### SMS
- ✅ Templates, Phone Book, Send (class/section/contact/file wise), delivery report + balance.

### Credential
- ✅ Transfer Certificate, Testimonial, Certification, Student & HRM ID cards, ID-card templates.

### Public Website CMS
- ✅ Website settings (🟡), Menus, Posts engine (page/result/news/notice/slider/homepage_person/teacher/staff/committee/gallery/instructions).

### Reporting & Printing (cross-cutting)
- ✅ Printable/PDF outputs across student, exam (marksheet/tabulation/admit), fees, accounts, attendance; jsPDF+autotable in the current client.

---

## B. IMPLIED BUSINESS REQUIREMENTS (must exist; no dedicated confirmed screen)

- 🔧 **Academic-session lifecycle**: opening/closing a session, "current session" selection, carry-forward.
- 🔧 **Student status machine**: active → transferred(TC)/left/passed-out/migrated; readmission.
- 🔧 **Enrolment history**: a student's class/section/roll changes across sessions (migration implies it).
- 🔧 **Fee ledger per student**: payable = config − waiver + fine; balance/dues; receipts; posting to GL.
- 🔧 **Result computation engine**: component→subject→grade→GPA(+4th subj)→position→pass/fail with per-class grade tables and merit rules.
- 🔧 **Roll/seat uniqueness** within class-section-session; **exam seat allocation** algorithm.
- 🔧 **Device punch ingestion** pipeline (raw punches → resolved daily attendance).
- 🔧 **SMS gateway** integration + balance accounting + delivery callbacks.
- 🔧 **Permission catalog** mapping every menu/action to a permission (the "Tools/Permissions" columns).
- 🔧 **Audit trail** for sensitive ops (mark edits, fee collection, user/permission changes) — expected in an ERP, not visibly surfaced.
- 🔧 **File storage** for photos, ID templates, post images, imports.

---

## C. RECOMMENDED IMPROVEMENTS (advised for the rebuild)

| # | Improvement | Rationale |
|---|---|---|
| R1 | **REST API + React SPA** replacing Blade/Livewire | brief mandate; enables mobile app, integrations, faster UX. |
| R2 | **Routine conflict detection** (teacher/room/section double-booking) | absent today; high-value, low-cost with the new model. |
| R3 | **Richer dashboard** (fees today, dues, exam progress, attendance %, notices) | current dashboard is 4 cards only. |
| R4 | **Guardian/Student portal** (results, fees, attendance, notices) | natural extension; reduces office load. |
| R5 | **Structured audit log** + activity feed | compliance & dispute resolution for marks/fees. |
| R6 | **Import/export standardisation** (typed CSV/XLSX templates with validation preview) | replaces ad-hoc "file wise" flows; fewer bad-data errors. |
| R7 | **Unified "Setup Resource" UX** for all reference tables | ~30 near-identical CRUD screens → one pattern (consistency, less code). |
| R8 | **Consistent, accessible design system** (Tailwind) | AdminLTE is dated; fix nav bugs, mobile, a11y. |
| R9 | **Idempotent, queued result processing & report generation** | large batches must not block requests. |
| R10 | **Fine-grained, cached permission checks** in both API and UI | matches observed model, done cleanly. |
| R11 | **Soft deletes + restore** on masters | data-loss protection (bulk delete exists today). |
| R12 | **Config-driven grading/merit rules** per branch | already per-class; formalise & validate. |

## D. FUTURE FEATURES (later phases / roadmap tail)

- Online fee payment (bKash/Nagad/SSLCommerz) + auto-reconciliation to GL.
- Mobile apps (teacher mark entry, guardian portal) on the same API.
- Library, Inventory/Asset, Transport, Hostel modules.
- Timetable auto-generation / optimisation.
- Analytics & BI (cohort performance, dropout risk, collection forecasting).
- Multi-channel notifications (push, email, WhatsApp) beyond SMS.
- Board/government reporting exports; question bank & online exams.
- Biometric/RFID gate + parent notification in real time.

---

## Module → rebuild-module mapping (preview of doc 03)

| Existing area | Rebuild module |
|---|---|
| Auth, Users, Roles, Permissions | `Auth`, `Users`, `AccessControl` |
| Branch select, settings | `Organizations`, `Branches`, `Settings` |
| Academic Year, Class/Shift/Section/Group/Category, Configs | `Academic` |
| Admission | `Admissions` |
| Student registration/update/migration/reports | `Students` |
| HRM | `Hr` |
| Attendance (staff+student, devices) | `Attendance` |
| Routine (class+exam) | `Routines` |
| Exam setup/config/marks/results/admit | `Examinations`, `Results` |
| Student fees | `Fees` |
| Double-entry ledger | `Accounting` |
| SMS | `Messaging` |
| Credentials (TC/testimonial/ID/cert) | `Credentials` |
| Website CMS (posts/menus) | `Cms` |
| Reports/print across modules | `Reporting` (shared) |
| Audit (implied) | `AuditLog` (shared) |
