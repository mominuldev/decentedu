# 01 — Existing Application Analysis (Phase 1)

All facts here are drawn from the crawl of the live app (250 pages). Where the demo instance
returned a `500`/empty screen (unconfigured demo data, not a missing feature) it is flagged
`[demo-errored]`. Inferences are flagged `[inferred]`.

## 0. Platform-level findings

| Aspect | Finding | Evidence |
|---|---|---|
| Framework | Laravel + Livewire | `wire:` markup; `safe_eduman_session`, `XSRF-TOKEN` cookies |
| UI kit | AdminLTE 3 / Bootstrap 4, jQuery, Select2, Tempus-Dominus date picker, jsPDF+autotable | asset manifest in `tech-fingerprint.json` |
| Rendering | Server-rendered Blade + Livewire; 2 real XHR endpoints only (`/admin/dashboard_data`, `/admin/student/get_group_by_section`) | `api-catalog.json` |
| DB | MySQL `[inferred]` (Laravel default; Bangladeshi shared-hosting norm) | — |
| Localisation | Bilingual EN/বাংলা; `name_bn` columns throughout | `semester/create` fields, top-bar toggle |
| Session frame | Active **branch** + **academic session** shown in top bar ("Demo IT School — Current Session:") | dashboard screenshot |
| Versioning | App version 3.0.5 | dashboard footer |

### Global navigation structure (authoritative sidebar, 12 modules)

```
Dashboard
Online Admission ── Setting(Admission Year, Class, Quota, Quota Config) · Report · Application List
Student ─────────── Setting(Academic Year, Class, Shift, Section, Group, Category, Class Config,
                            Group Config) · Registration(Create, Delete) · Update(Multiple, Photo,
                            Class Info, Address) · Migration(General, Merit, Push Back) ·
                            Report(9 report types) · Student List
HRM ─────────────── Setting(Designation, Section) · Registration(Single, Multiple) ·
                            Update(Update, Multiple, Subject Assign) · Employee List
Attendance ──────── HRM Attendance(Setting: Time Config, Device ID Map, Report Generate; Report) ·
                            Student Attendance(Setting: Take Period, Period Time Config, Device ID
                            Map, SMS Template; Report; Take Attendance; Update Attendance)
Credential ──────── TC(Add, Download) · Testimonial(Add, Download) · Certification ·
                            Student ID Card · HRM ID Card
Routine ─────────── Exam Routine(List, Create) · Class Routine(List, Teacher Routine)
Class Assessment ── (grouping header)
Exam ────────────── Setting(Exam, Subject, Short Code, Grade) · Config(Exam, Mark, Exam Final,
                            Fourth Subject, Admit Instruction, Signature, Class Teacher) ·
                            Marks(Input, Update, Delete) · Result Process(General, Merit, Final) ·
                            Result(Merit CW/SW, Fail CW/SW, Marksheet, Marksheet Final, Tabulation,
                            Short Tabulation) · Admit/Seat Plan(Attendance Sheet, Admit Card, Seat Plan)
Accounts ────────── Student Accounts(Setting: Head, Sub Head, Fee Waiver; Config: Sub Head, Fee,
                            Fee Waiver, Time; Fee Collection: Collect, Remove; Reports) ·
                            General Accounts(Ledger, Receive, Payment, Contra, Journal; Reports:
                            Trial Balance, Income Statement, Voucher List, Journal Report)
SMS & Notification ─ SMS Template · Phone Book · SMS Send(Class/Section/Contact/File Wise) · Report
Web Site ────────── Pages, Results, News & Events, Notice, Slider, Homepage Person, Teacher, Staff,
                            Committee, Photo Gallery, Instructions (CMS via `posts?type=…`)
Settings ────────── Users, Basic Settings, Profile
```

> **Existing UX bug noted:** in the live sidebar the "Web Site" labels are offset from their hrefs
> by one (e.g. label "Result" → `posts?type=page`), and "Settings → Logout" points at `/admin/users`.
> The rebuild fixes these; documented so the mapping isn't copied blindly (brief Rule 7/9).

---

## 1. Dashboard

- **Purpose:** at-a-glance branch snapshot for the current session.
- **Screens:** `/admin`. KPI cards: **SMS Balance, Total Students (44), Present Students, Absent
  Students**. Data via `GET /admin/dashboard_data` → `{total_students, prasent_students, absent_students}`.
- **Roles:** all authenticated admin users (scoped to active branch).
- **Business rules:** counts are **branch + active-session** scoped; present/absent derive from today's
  student attendance.
- **Improvement targets:** thin today (4 cards). Rebuild adds fee-collection-today, dues, exam status,
  recent notices (see doc 07).

## 2. Online Admission

- **Purpose:** pre-enrolment admission pipeline (prospective students apply, are processed by quota,
  then converted to students).
- **Screens:** Setup → **Admission Year**, **Admission Class**, **Quota**, **Quota Config**; **Application
  List** (`/admin/admission/application`). Setup pages `[demo-errored 500]` (demo not configured).
- **Entities `[inferred]`:** `admission_year`, `admission_class`, `quota`, `quota_config`,
  `admission_application` (applicant bio + chosen class/quota + status).
- **CRUD:** Year/Class/Quota/QuotaConfig are CRUD reference tables; Applications are create→review→
  select/reject→convert-to-student.
- **Statuses `[inferred]`:** application `pending → selected/waiting/rejected → admitted`.
- **Relationships:** feeds **Student** (admitted applicant → student registration); uses **Academic**
  (class), **Quota**.
- **Business rules `[inferred]`:** quota caps seats per category; merit ordering for selection.

## 3. Student Management

The largest module. Terminology: **Class** (route `semester`), **Group** (Science/Commerce/Arts/etc.),
**Shift** (Morning/Day), **Section**, **Category** (e.g. residential/quota class), **Academic Year**.

### 3a. Setup (reference data, all branch+scoped CRUD with `serial` + `status`)

| Screen | Fields (confirmed) | Notes |
|---|---|---|
| Academic Year | (year name, current flag) | session anchor |
| Class (`semester`) | `name`, `name_bn`, `serial`, `status` | bilingual |
| Shift | `name`, `serial`, `status` | |
| Section | `name`, `serial`, `status` | |
| Group | `name`, `serial`, `status` | |
| Category | `name`, `serial`, `status` | |
| Class Config | `class_id`, `shift_id`, `section_id`, `serial`, `status` | **the pivot that makes a "class-section" real** |
| Group Config | `class_id`, `group_id`, `serial`, `status` | which groups exist in a class |

> **Key modelling insight:** `class_config` = (class × shift × section) is the concrete teaching unit
> that students enrol into and that everything (attendance, exam, fees, routine) hangs off. `group_config`
> = (class × group). These two pivots are central to the schema (doc 04).

### 3b. Registration / Update / Migration

- **Registration:** bulk grid create (`/registration/multiple_create`) — pick academic_year/section/
  group/category, then rows of **class_roll, name, sex, religion, fathersName, mothersName, mobile,
  fatherMobile, motherMobile, blood_group** (+ photo upload). Single-student create `[demo-errored 500]`.
  Also **Delete** (bulk).
- **Update:** Multiple edit, **Photo Update**, **Class Info** update, **Address** edit — all bulk grids.
- **Migration:** **General**, **Merit**, **Push Back** — promote/demote/roll students between
  classes/sessions (year-end promotion workflow). Push Back = reverse a promotion.
- **Required fields (confirmed):** roll, name, sex, religion, father/mother name, mobile, father/mother
  mobile. Optional: blood group, photo.

### 3c. Student Reports (9)

Class Wise, Section Wise, Group Wise, Category Wise, Gender Wise, Religion Wise, Section Summary,
Student Profile, Student Summary. All filter by academic year + class/section/etc. and render printable
tables (2-table pattern = filter form + results).

- **Relationships:** Student ↔ ClassConfig (enrolment), Academic Year, Group, Category; consumed by
  Attendance, Exam, Fees, SMS, Credentials.
- **Edge cases:** roll uniqueness within class-section-session; mid-year transfers (TC); re-admission.

## 4. HRM (Staff / Teachers)

- **Purpose:** employee master + teaching assignments.
- **Screens:** Setting → **Designation**, **Section (dept)**; Registration → **Single**
  (`employee/create`), **Multiple** `[demo-errored 500]`; Update → **Update**, **Multiple**, **Subject
  Assign** (`subject_teacher`); **Employee List**.
- **Employee fields (confirmed):** `name, designation_id, section_id, father, mother, email, mobile,
  gender, religion, blood_group, address`.
- **Subject Teacher Assign:** maps teachers → subjects (feeds routine, mark-entry permissions).
- **Relationships:** Designation, HRM Section (department); Employee → Attendance (device map), Exam
  (class teacher config, subject teacher), Routine, Credentials (HRM ID card).
- **Statuses `[inferred]`:** active/inactive employee.

## 5. Attendance (biometric)

Two parallel sub-systems: **HRM (staff)** and **Student**, both device-driven.

- **Settings:** **Time Config** (in/out windows, late rules), **Device ID Map** (map biometric device
  user-ids → employee/student), **Take Period** (student: which class periods are attended), **Report
  Generate** (materialise daily attendance from raw device punches).
- **Operations:** Student → **Take Attendance**, **Update Attendance** (manual override).
- **Reports:** Daily Report, Daily Report Single, Daily Report Single All.
- **SMS integration:** absentee **SMS Template** (`/sms/smsTemplate/attendence`) → auto-SMS guardians.
- **Business rules:** device punch → mapped person → time-config decides present/late/absent → optional
  guardian SMS. Present/Absent feed the dashboard.
- **Relationships:** Employee, Student, ClassConfig, SMS.
- **Edge cases:** unmapped device id; multiple punches/day; holidays; period-wise vs day-wise.

## 6. Credential (documents)

- **Purpose:** generate printable official documents.
- **Screens:** **TC** (Transfer Certificate: add → `academic_year, section, student, TcReason,
  LeftDate` → download), **Testimonial** (add/download), **Certification** (download), **Student ID
  Card**, **HRM ID Card**, **ID Card Template** (`accessories/id_card_template`, template CRUD).
- **Business rules:** TC marks a student as left (LeftDate) — interacts with student status. ID cards
  render from a configurable template.
- **Relationships:** Student, Employee, branch signature/logo settings.

## 7. Routine / Timetable

- **Exam Routine:** create per academic_year/class/group/exam → per-subject **exam_date, start_time,
  end_time, room_no, exam_session** (confirmed fields). List + printable.
- **Class Routine:** weekly class timetable (`/admin/class-routine`) + **Teacher Routine** print
  (per-teacher schedule).
- **Business rules:** exam routine tied to an exam + subject config; class routine maps class-config ×
  period × subject × teacher.
- **Conflict detection:** not evidenced in the current UI → **improvement** (doc 02).
- **Relationships:** Exam, Subject, ClassConfig, Employee (teacher), rooms.

## 8. Exam & Results (deepest module)

- **Setup:** **Exam** (`name, exam_type_id, serial, status`), **Subject** (`name, serial, status`),
  **Short Code** (mark components e.g. CQ/MCQ/Practical), **Grade** (per-class: `grade, grade_point,
  grade_range` — GPA table).
- **Confirmed enumerations (from captured option lists):**
  - `exam_type` ∈ **Weekly, Monthly, Final, Grand Final** → terms roll up: Weekly/Monthly → Final →
    Grand Final (multi-term combination is real, via Final Mark Config).
  - `merit_process_type` ∈ **Total Mark (Sequential)**, **Total Mark (Non Sequential)**, **Grade Point
    (Sequential)**, **Grade Point (Non Sequential)** — the four merit-ranking algorithms.
  - **Grade scale is per class** (grade page keys by Class: Six…Ten), confirming `grades(class_id, …)`.
  - Reference `status` ∈ Active/Inactive; `sex` ∈ Male/Female/Others; `religion` ∈ Islam/Hindu/
    Christian/Buddhist/Others; `blood_group` ∈ A±/B±/AB±/O±/Unknown; `category` includes "General".
  - **Section labels are composite** (e.g. "Six-A-Day" = Class Six · Section A · Day shift), directly
    validating the `class_config = class × shift × section` backbone.
- **Config:** **Exam Config** (per class: which exams + `merit_process_type`), **Subject Config**,
  **Mark Config** (per class/group/exam/subject: `total_marks, pass_mark, acceptance, sc_merge` per
  short-code — i.e. component-level mark rules), **Final Mark Config** (combine term exams → final),
  **Fourth Subject** assign (Bangladeshi 4th-subject GPA rule), **Admit Instruction**, **Signature**
  config (marksheet signatories), **Class Teacher** config.
- **Marks:** **Input** (grid: pick year/section/group/exam/subject → per-student `is_absent` +
  `mark_list[student][mark_config]`), **Update**, **Delete**.
- **Result Process:** **General**, **Merit**, **Final** (batch compute results/positions).
- **Results/Reports:** Merit Class/Section Wise, Fail Class/Section Wise, **Marksheet**, **Marksheet
  Final**, **Tabulation Sheet**, **Short Tabulation Sheet**.
- **Admit/Seat Plan:** Attendance Sheet, **Admit Card**, **Seat Plan**.
- **Business rules (confirmed/inferred):** component marks → subject total vs pass_mark & acceptance →
  grade via per-class grade table → GPA with 4th-subject adjustment → merit position by merit_process_type
  → pass/fail. Multi-term "final" combines exams by weight.
- **Relationships:** Student, ClassConfig, Group, Subject, Employee (class teacher/signatory), branch
  grade scale. This is the analytical heart of the system.

## 9. Accounts (two sub-systems)

### 9a. Student Accounts (fee billing)
- **Setup:** **Head**, **Sub Head**, **Fee Waiver**.
- **Config:** **Sub Head Config**, **Fee Config** (per `academic_year/class/group/category/head`:
  `amount`, plus per-subhead `fee_amount, fine_amount`), **Fee Waiver Config** (+ waiver list), **Time
  Config** (subhead due dates / fine schedule).
- **Collection:** **Collect Fee** `[demo-errored 500]`, **Remove Fee**.
- **Reports:** Paid List, Collection Details (both `[demo-errored 500]`), **Section Due Summary**, **Fee
  Head Due List**.
- **Business rules:** fees are configured by class/group/category; waivers reduce payable; fines accrue
  after time-config due dates; collection generates money receipts and (likely) posts to accounting.

### 9b. General Accounts (double-entry ledger)
- **Ledger** (`accounts/ledger`) `[demo-errored 500]`; **Vouchers**: **Receive** (`ledger_ids[],
  amounts[], total, date, note`), **Payment**, **Contra**, **Journal** (`ledger_ids[], debit_amounts[],
  credit_amounts[]` — true double-entry).
- **Reports:** **Trial Balance, Income Statement, Voucher List, Journal Report** (Cash Summary also
  present).
- **Business rules:** full double-entry accounting; fee collection feeds receive vouchers; branch-scoped
  chart of accounts.

## 10. SMS & Notification

- **SMS Template** (CRUD, incl. attendance template), **Phone Book / Contacts**, **Send** (Class Wise,
  Section Wise, Contact Wise, **File Wise**/CSV), **Report** (delivery log + balance).
- **Business rules:** SMS balance is a paid resource (shown on dashboard); templates support variables;
  sends are audience-filtered (class/section/contact/file).
- **Relationships:** Students (mobiles), Attendance (auto absentee SMS), Exam (result SMS `[inferred]`).

## 11. Web Site (public CMS)

- **Purpose:** each branch has a **public school website** driven from here.
- **Screens:** **Basic/Website Settings** `[demo-errored 500]`, **Menus**, and a generic **Posts** engine
  keyed by `type=`: page, result, news, notice, slider, homepage_person, teacher, staff, committee,
  gallery, instructions. Post fields (confirmed): `title, body, description, keywords, image/files`.
- **Business rules:** one polymorphic `posts` table with a `type` discriminator drives all public
  content; menus build the public nav.
- **Relationships:** branch settings (logo, theme), public site rendering (out of admin scope).

## 12. System Administration

- **Users** (`/admin/users`): list with **Name, Email, Branch, Role, Permissions, Tools**; create/edit
  assigns branch + role + granular permissions.
- **Roles observed:** **Super Admin**, **Admin** (per-user granular permission overrides).
- **Basic Settings** (branch identity/config), **Profile / Edit Profile** (own account, password).
- **Auth:** `/login`, **Forgot Password** (`/forgot-password`), "keep me signed in", logout.
- **Multi-branch:** **Select Branch** (`/admin/home`) — choose active branch when a user has several.

---

## Cross-cutting observations (feed the rebuild)

1. **Everything is scoped by branch + academic year.** These two are ambient context, not per-form
   params — the rebuild must model them as first-class request context (doc 05).
2. **`class_config` and `group_config` are the backbone** join tables; students, attendance, marks,
   fees, routines all reference them.
3. **Reference-data pattern is uniform:** `name (+name_bn) + serial + status` with list/create/edit.
   This screams a reusable "SetupResource" abstraction (doc 03/07).
4. **Bulk grid operations dominate** (registration, marks, fees) — the React design must nail
   high-performance editable tables (doc 07).
5. **Print/PDF is mission-critical** (marksheets, admit cards, TC, ID cards, tabulation) — a first-class
   reporting subsystem is required (doc 08).
6. **Bilingual (EN/বাংলা)** is pervasive → i18n + `_bn` fields from day one.
7. **The 500s are demo-data gaps, not missing features** — the routes, menus and configs prove the
   features exist; they must be reproduced.
