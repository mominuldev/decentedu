<?php

use App\Http\Controllers\Api\Academic\ClassConfigController;
use App\Http\Controllers\Api\Academic\SetupController;
use App\Http\Controllers\Api\Accounting\AccountingReportController;
use App\Http\Controllers\Api\Accounting\LedgerAccountController;
use App\Http\Controllers\Api\Accounting\VoucherController;
use App\Http\Controllers\Api\Admissions\AdmissionYearController;
use App\Http\Controllers\Api\Admissions\ApplicationController;
use App\Http\Controllers\Api\Admissions\QuotaController;
use App\Http\Controllers\Api\Attendance\DeviceController;
use App\Http\Controllers\Api\Attendance\DeviceMapController;
use App\Http\Controllers\Api\Attendance\EmployeeAttendanceController;
use App\Http\Controllers\Api\Attendance\HolidayController;
use App\Http\Controllers\Api\Attendance\PunchController;
use App\Http\Controllers\Api\Attendance\StudentAttendanceController;
use App\Http\Controllers\Api\Attendance\TimeConfigController;
use App\Http\Controllers\Api\Audit\AuditLogController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\Cms\AssetController;
use App\Http\Controllers\Api\Cms\EventController;
use App\Http\Controllers\Api\Cms\MediaFolderController;
use App\Http\Controllers\Api\Cms\MenuController;
use App\Http\Controllers\Api\Cms\NoticeController;
use App\Http\Controllers\Api\Cms\PageController;
use App\Http\Controllers\Api\Cms\PostController;
use App\Http\Controllers\Api\Cms\Public\EventController as PublicEventController;
use App\Http\Controllers\Api\Cms\Public\MenuController as PublicMenuController;
use App\Http\Controllers\Api\Cms\Public\NoticeController as PublicNoticeController;
use App\Http\Controllers\Api\Cms\Public\PageController as PublicPageController;
use App\Http\Controllers\Api\Cms\Public\PostController as PublicPostController;
use App\Http\Controllers\Api\Cms\Public\TermController as PublicTermController;
use App\Http\Controllers\Api\Cms\RedirectController;
use App\Http\Controllers\Api\Cms\TaxonomyController;
use App\Http\Controllers\Api\Cms\TermController;
use App\Http\Controllers\Api\Credentials\CertificateController;
use App\Http\Controllers\Api\Credentials\IdCardController;
use App\Http\Controllers\Api\Credentials\IdCardTemplateController;
use App\Http\Controllers\Api\Credentials\TestimonialController;
use App\Http\Controllers\Api\Credentials\TransferCertificateController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Examinations\AdmitController;
use App\Http\Controllers\Api\Examinations\AdmitInstructionController;
use App\Http\Controllers\Api\Examinations\ClassTeacherConfigController;
use App\Http\Controllers\Api\Examinations\ExamConfigController;
use App\Http\Controllers\Api\Examinations\ExamRoutineController;
use App\Http\Controllers\Api\Examinations\FourthSubjectController;
use App\Http\Controllers\Api\Examinations\GradeController;
use App\Http\Controllers\Api\Examinations\MarkConfigController;
use App\Http\Controllers\Api\Examinations\MarksController;
use App\Http\Controllers\Api\Examinations\ResultController;
use App\Http\Controllers\Api\Examinations\SetupController as ExaminationsSetupController;
use App\Http\Controllers\Api\Examinations\SignatureController;
use App\Http\Controllers\Api\Fees\FeeCollectionController;
use App\Http\Controllers\Api\Fees\FeeConfigController;
use App\Http\Controllers\Api\Fees\FeeReportController;
use App\Http\Controllers\Api\Fees\FeeTimeConfigController;
use App\Http\Controllers\Api\Fees\FeeWaiverConfigController;
use App\Http\Controllers\Api\Fees\SetupController as FeesSetupController;
use App\Http\Controllers\Api\Hr\EmployeeController;
use App\Http\Controllers\Api\Hr\TeacherController;
use App\Http\Controllers\Api\Hr\SetupController as HrSetupController;
use App\Http\Controllers\Api\Messaging\ContactController;
use App\Http\Controllers\Api\Messaging\SendController;
use App\Http\Controllers\Api\Messaging\TemplateController;
use App\Http\Controllers\Api\Reporting\ReportController;
use App\Http\Controllers\Api\Routines\ClassRoutineController;
use App\Http\Controllers\Api\Routines\PeriodController;
use App\Http\Controllers\Api\Students\StudentController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\Users\RoleController;
use App\Http\Controllers\Api\Users\UserController;
use App\Support\Reporting\ReportRegistry;
use Illuminate\Support\Facades\Route;

// Slugs handled by the generic setup controller.
$setupSlugs = 'academic-years|classes|shifts|sections|groups|categories|subjects';

Route::prefix('v1')->group(function () use ($setupSlugs) {
    // Public
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);

    // Rendered public site (Next.js frontend). Unauthenticated, read-only, serving the
    // published CMS content of the single branch pinned in config('cms.public_branch_id').
    // Reuses the same Public\* controllers as the in-app authenticated preview endpoints;
    // the only difference is how the branch is resolved (config vs. session). The
    // pages/{path} wildcard is last so it can't swallow the sibling collection routes.
    Route::prefix('site')->middleware('public-branch')->group(function () {
        Route::get('pages', [PublicPageController::class, 'index']);
        Route::get('posts', [PublicPostController::class, 'index']);
        Route::get('posts/{slug}', [PublicPostController::class, 'show']);
        Route::get('taxonomies/{taxonomy}/terms', [PublicTermController::class, 'index']);
        Route::get('terms/{taxonomy}/{slug}', [PublicTermController::class, 'show']);
        Route::get('notices', [PublicNoticeController::class, 'index']);
        Route::get('notices/{slug}', [PublicNoticeController::class, 'show']);
        Route::get('events', [PublicEventController::class, 'index']);
        Route::get('events/{slug}', [PublicEventController::class, 'show']);
        Route::get('menus/{key}', [PublicMenuController::class, 'show']);
        Route::get('pages/{path}', [PublicPageController::class, 'show'])->where('path', '.*');
    });

    // Authenticated (Sanctum SPA cookie session) + active-branch context.
    Route::middleware(['auth:sanctum', 'branch'])->group(function () use ($setupSlugs) {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);
        Route::get('auth/sessions', [AuthController::class, 'sessions']);
        Route::delete('auth/sessions/{id}', [AuthController::class, 'revokeSession']);
        Route::post('branch/switch', [BranchController::class, 'switch']);

        // ---- Users & Roles -----------------------------------------------------
        // GET routes stay open to any authenticated user (the SPA needs role/permission
        // names to render its own nav); only mutations require users.manage.
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('{id}', [UserController::class, 'show'])->whereNumber('id');
            Route::middleware('permission:users.manage')->group(function () {
                Route::post('/', [UserController::class, 'store']);
                Route::match(['put', 'patch'], '{id}', [UserController::class, 'update'])->whereNumber('id');
                Route::post('{id}/deactivate', [UserController::class, 'deactivate'])->whereNumber('id');
                Route::post('{id}/force-reset', [UserController::class, 'forceReset'])->whereNumber('id');
            });
        });

        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleController::class, 'index']);
            Route::get('permissions', [RoleController::class, 'permissions']);
            Route::match(['put', 'patch'], '{id}/permissions', [RoleController::class, 'updatePermissions'])
                ->whereNumber('id')->middleware('permission:users.manage');
        });

        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');

        Route::post('uploads', [UploadController::class, 'store']);
        Route::get('uploads/{path}', [UploadController::class, 'show'])->where('path', '.*');

        Route::get('dashboard', [DashboardController::class, 'index']);

        // ---- Academic module -------------------------------------------------
        Route::prefix('academic')->middleware('permission:academic.manage')->group(function () use ($setupSlugs) {
            // Class Config (Class × Shift × Section)
            Route::get('class-configs/options', [ClassConfigController::class, 'options']);
            Route::get('class-configs', [ClassConfigController::class, 'index']);
            Route::post('class-configs', [ClassConfigController::class, 'store']);
            Route::match(['put', 'patch'], 'class-configs/{id}', [ClassConfigController::class, 'update'])->whereNumber('id');
            Route::delete('class-configs/{id}', [ClassConfigController::class, 'destroy'])->whereNumber('id');

            // Uniform setup resources
            Route::get('{resource}', [SetupController::class, 'index'])->where('resource', $setupSlugs);
            Route::post('{resource}', [SetupController::class, 'store'])->where('resource', $setupSlugs);
            Route::get('{resource}/{id}', [SetupController::class, 'show'])->where('resource', $setupSlugs)->whereNumber('id');
            Route::match(['put', 'patch'], '{resource}/{id}', [SetupController::class, 'update'])->where('resource', $setupSlugs)->whereNumber('id');
            Route::delete('{resource}/{id}', [SetupController::class, 'destroy'])->where('resource', $setupSlugs)->whereNumber('id');
        });

        // ---- Students module -------------------------------------------------
        Route::prefix('students')->middleware('permission:students.manage')->group(function () {
            Route::get('/', [StudentController::class, 'index']);
            Route::post('/', [StudentController::class, 'store']);
            Route::post('bulk-register', [StudentController::class, 'bulkRegister'])->middleware('throttle:bulk-import');
            Route::post('migrate', [StudentController::class, 'migrate']);
            Route::get('{id}', [StudentController::class, 'show'])->whereNumber('id');
            Route::match(['put', 'patch'], '{id}', [StudentController::class, 'update'])->whereNumber('id');
            Route::delete('{id}', [StudentController::class, 'destroy'])->whereNumber('id');
        });

        // ---- Admissions module -----------------------------------------------
        // Online admission pipeline: drives (years) + seat quotas + applications, ending in
        // applicant → student conversion (docs/02 §Admission).
        Route::prefix('admissions')->middleware('permission:admissions.manage')->group(function () {
            // Setup: admission years (drives) and seat quotas.
            Route::get('years', [AdmissionYearController::class, 'index']);
            Route::post('years', [AdmissionYearController::class, 'store']);
            Route::match(['put', 'patch'], 'years/{id}', [AdmissionYearController::class, 'update'])->whereNumber('id');
            Route::delete('years/{id}', [AdmissionYearController::class, 'destroy'])->whereNumber('id');

            Route::get('quotas', [QuotaController::class, 'index']);
            Route::post('quotas', [QuotaController::class, 'store']);
            Route::match(['put', 'patch'], 'quotas/{id}', [QuotaController::class, 'update'])->whereNumber('id');
            Route::delete('quotas/{id}', [QuotaController::class, 'destroy'])->whereNumber('id');

            // Applications + pipeline actions.
            Route::get('applications/stats', [ApplicationController::class, 'stats']);
            Route::get('applications', [ApplicationController::class, 'index']);
            Route::post('applications', [ApplicationController::class, 'store']);
            Route::get('applications/{id}', [ApplicationController::class, 'show'])->whereNumber('id');
            Route::match(['put', 'patch'], 'applications/{id}', [ApplicationController::class, 'update'])->whereNumber('id');
            Route::post('applications/{id}/status', [ApplicationController::class, 'updateStatus'])->whereNumber('id');
            Route::post('applications/{id}/convert', [ApplicationController::class, 'convert'])->whereNumber('id');
            Route::delete('applications/{id}', [ApplicationController::class, 'destroy'])->whereNumber('id');
        });

        // ---- HR module -------------------------------------------------------
        $hrSetupSlugs = 'designations|hr-sections';

        Route::prefix('hr')->middleware('permission:hr.manage')->group(function () use ($hrSetupSlugs) {
            // HR setup resources (designations, departments)
            Route::get('{resource}', [HrSetupController::class, 'index'])->where('resource', $hrSetupSlugs);
            Route::post('{resource}', [HrSetupController::class, 'store'])->where('resource', $hrSetupSlugs);
            Route::get('{resource}/{id}', [HrSetupController::class, 'show'])->where('resource', $hrSetupSlugs)->whereNumber('id');
            Route::match(['put', 'patch'], '{resource}/{id}', [HrSetupController::class, 'update'])->where('resource', $hrSetupSlugs)->whereNumber('id');
            Route::delete('{resource}/{id}', [HrSetupController::class, 'destroy'])->where('resource', $hrSetupSlugs)->whereNumber('id');

            // Employees
            Route::prefix('employees')->group(function () {
                Route::get('/', [EmployeeController::class, 'index']);
                Route::post('/', [EmployeeController::class, 'store']);
                Route::get('{id}', [EmployeeController::class, 'show'])->whereNumber('id');
                Route::match(['put', 'patch'], '{id}', [EmployeeController::class, 'update'])->whereNumber('id');
                Route::delete('{id}', [EmployeeController::class, 'destroy'])->whereNumber('id');
                Route::post('{id}/assign-subject', [EmployeeController::class, 'assignSubject'])->whereNumber('id');
                Route::delete('{id}/subject-assignments/{assignmentId}', [EmployeeController::class, 'removeSubject'])
                    ->whereNumber('id')
                    ->whereNumber('assignmentId');
            });

            // Teachers (dedicated endpoints for teachers only)
            Route::prefix('teachers')->group(function () {
                Route::get('/', [TeacherController::class, 'index']);
                Route::get('available', [TeacherController::class, 'available']);
                Route::get('subject/{subjectId}', [TeacherController::class, 'bySubject'])->whereNumber('subjectId');
                Route::get('class/{classConfigId}', [TeacherController::class, 'byClassConfig'])->whereNumber('classConfigId');
                Route::get('{id}', [TeacherController::class, 'show'])->whereNumber('id');
                Route::get('{id}/classes', [TeacherController::class, 'classes'])->whereNumber('id');
            });
        });

        // ---- Routines module --------------------------------------------------
        Route::prefix('routines')->middleware('permission:routines.manage')->group(function () {
            Route::get('periods', [PeriodController::class, 'index']);
            Route::post('periods', [PeriodController::class, 'store']);
            Route::match(['put', 'patch'], 'periods/{id}', [PeriodController::class, 'update'])->whereNumber('id');
            Route::delete('periods/{id}', [PeriodController::class, 'destroy'])->whereNumber('id');

            Route::get('class-configs/{classConfigId}/options', [ClassRoutineController::class, 'options'])->whereNumber('classConfigId');
            Route::get('class-configs/{classConfigId}', [ClassRoutineController::class, 'forClassConfig'])->whereNumber('classConfigId');
            Route::get('teachers/{employeeId}', [ClassRoutineController::class, 'forTeacher'])->whereNumber('employeeId');
            Route::post('/', [ClassRoutineController::class, 'store']);
            Route::match(['put', 'patch'], '{id}', [ClassRoutineController::class, 'update'])->whereNumber('id');
            Route::delete('{id}', [ClassRoutineController::class, 'destroy'])->whereNumber('id');
        });

        // ---- Attendance module --------------------------------------------------
        Route::prefix('attendance')->middleware('permission:attendance.manage')->group(function () {
            Route::get('holidays', [HolidayController::class, 'index']);
            Route::post('holidays', [HolidayController::class, 'store']);
            Route::match(['put', 'patch'], 'holidays/{id}', [HolidayController::class, 'update'])->whereNumber('id');
            Route::delete('holidays/{id}', [HolidayController::class, 'destroy'])->whereNumber('id');

            Route::get('devices', [DeviceController::class, 'index']);
            Route::post('devices', [DeviceController::class, 'store']);
            Route::match(['put', 'patch'], 'devices/{id}', [DeviceController::class, 'update'])->whereNumber('id');
            Route::delete('devices/{id}', [DeviceController::class, 'destroy'])->whereNumber('id');

            Route::get('device-maps', [DeviceMapController::class, 'index']);
            Route::post('device-maps', [DeviceMapController::class, 'store']);
            Route::delete('device-maps/{id}', [DeviceMapController::class, 'destroy'])->whereNumber('id');

            Route::get('time-configs', [TimeConfigController::class, 'index']);
            Route::post('time-configs', [TimeConfigController::class, 'store']);
            Route::match(['put', 'patch'], 'time-configs/{id}', [TimeConfigController::class, 'update'])->whereNumber('id');
            Route::delete('time-configs/{id}', [TimeConfigController::class, 'destroy'])->whereNumber('id');

            Route::post('punches', [PunchController::class, 'store']);
            Route::post('punches/process', [PunchController::class, 'process']);

            Route::prefix('students')->group(function () {
                Route::get('/', [StudentAttendanceController::class, 'index']);
                Route::post('take', [StudentAttendanceController::class, 'take']);
                Route::get('report', [StudentAttendanceController::class, 'report']);
                Route::match(['put', 'patch'], '{id}', [StudentAttendanceController::class, 'update'])->whereNumber('id');
            });

            Route::prefix('employees')->group(function () {
                Route::get('/', [EmployeeAttendanceController::class, 'index']);
                Route::post('take', [EmployeeAttendanceController::class, 'take']);
                Route::get('report', [EmployeeAttendanceController::class, 'report']);
                Route::match(['put', 'patch'], '{id}', [EmployeeAttendanceController::class, 'update'])->whereNumber('id');
            });
        });

        // ---- Examinations module --------------------------------------------
        $examSetupSlugs = 'exams|short-codes';

        Route::prefix('examinations')->middleware('permission:examinations.manage')->group(function () use ($examSetupSlugs) {
            // Setup: exams, short-codes (uniform), grades (per-class scale)
            Route::get('{resource}', [ExaminationsSetupController::class, 'index'])->where('resource', $examSetupSlugs);
            Route::post('{resource}', [ExaminationsSetupController::class, 'store'])->where('resource', $examSetupSlugs);
            Route::get('{resource}/{id}', [ExaminationsSetupController::class, 'show'])->where('resource', $examSetupSlugs)->whereNumber('id');
            Route::match(['put', 'patch'], '{resource}/{id}', [ExaminationsSetupController::class, 'update'])->where('resource', $examSetupSlugs)->whereNumber('id');
            Route::delete('{resource}/{id}', [ExaminationsSetupController::class, 'destroy'])->where('resource', $examSetupSlugs)->whereNumber('id');

            Route::get('grades', [GradeController::class, 'index']);
            Route::post('grades', [GradeController::class, 'store']);
            Route::match(['put', 'patch'], 'grades/{id}', [GradeController::class, 'update'])->whereNumber('id');
            Route::delete('grades/{id}', [GradeController::class, 'destroy'])->whereNumber('id');

            // Config
            Route::get('exam-configs', [ExamConfigController::class, 'index']);
            Route::post('exam-configs', [ExamConfigController::class, 'store']);
            Route::match(['put', 'patch'], 'exam-configs/{id}', [ExamConfigController::class, 'update'])->whereNumber('id');
            Route::delete('exam-configs/{id}', [ExamConfigController::class, 'destroy'])->whereNumber('id');

            Route::get('mark-configs/options', [MarkConfigController::class, 'options']);
            Route::get('mark-configs', [MarkConfigController::class, 'index']);
            Route::post('mark-configs', [MarkConfigController::class, 'save']);
            Route::delete('mark-configs/{id}', [MarkConfigController::class, 'destroy'])->whereNumber('id');

            Route::get('fourth-subjects', [FourthSubjectController::class, 'index']);
            Route::post('fourth-subjects', [FourthSubjectController::class, 'save']);

            Route::get('class-teacher-configs', [ClassTeacherConfigController::class, 'index']);
            Route::post('class-teacher-configs', [ClassTeacherConfigController::class, 'store']);
            Route::delete('class-teacher-configs/{id}', [ClassTeacherConfigController::class, 'destroy'])->whereNumber('id');

            Route::get('signatures', [SignatureController::class, 'index']);
            Route::post('signatures', [SignatureController::class, 'store']);
            Route::match(['put', 'patch'], 'signatures/{id}', [SignatureController::class, 'update'])->whereNumber('id');
            Route::delete('signatures/{id}', [SignatureController::class, 'destroy'])->whereNumber('id');

            Route::get('admit-instructions', [AdmitInstructionController::class, 'show']);
            Route::match(['put', 'patch'], 'admit-instructions', [AdmitInstructionController::class, 'update']);

            // Exam routine
            Route::get('exam-routine/options', [ExamRoutineController::class, 'options']);
            Route::get('exam-routine', [ExamRoutineController::class, 'index']);
            Route::post('exam-routine', [ExamRoutineController::class, 'store']);
            Route::match(['put', 'patch'], 'exam-routine/{id}', [ExamRoutineController::class, 'update'])->whereNumber('id');
            Route::delete('exam-routine/{id}', [ExamRoutineController::class, 'destroy'])->whereNumber('id');

            // Marks input
            Route::get('marks/grid', [MarksController::class, 'grid']);
            Route::post('marks', [MarksController::class, 'save']);

            // Result processing
            Route::post('results/general-process', [ResultController::class, 'generalProcess']);
            Route::post('results/final-process', [ResultController::class, 'finalProcess']);
            Route::post('results/merit-process', [ResultController::class, 'meritProcess']);

            // Reports
            Route::get('results/marksheet', [ResultController::class, 'marksheet']);
            Route::get('results/tabulation-sheet', [ResultController::class, 'tabulationSheet']);
            Route::get('results/merit-list', [ResultController::class, 'meritList']);
            Route::get('results/fail-list', [ResultController::class, 'failList']);

            // Admit
            Route::get('admit/card', [AdmitController::class, 'admitCard']);
            Route::post('admit/seat-plan', [AdmitController::class, 'seatPlan']);
            Route::get('admit/attendance-sheet', [AdmitController::class, 'attendanceSheet']);
        });

        // ---- Fees module ------------------------------------------------------
        $feeSetupSlugs = 'heads|sub-heads|waivers';

        Route::prefix('fees')->middleware('permission:fees.manage')->group(function () use ($feeSetupSlugs) {
            // Setup: heads, sub-heads, waivers (uniform)
            Route::get('{resource}', [FeesSetupController::class, 'index'])->where('resource', $feeSetupSlugs);
            Route::post('{resource}', [FeesSetupController::class, 'store'])->where('resource', $feeSetupSlugs);
            Route::get('{resource}/{id}', [FeesSetupController::class, 'show'])->where('resource', $feeSetupSlugs)->whereNumber('id');
            Route::match(['put', 'patch'], '{resource}/{id}', [FeesSetupController::class, 'update'])->where('resource', $feeSetupSlugs)->whereNumber('id');
            Route::delete('{resource}/{id}', [FeesSetupController::class, 'destroy'])->where('resource', $feeSetupSlugs)->whereNumber('id');

            // Fee structure (payable amount per class_config x sub_head x academic_year)
            Route::get('configs', [FeeConfigController::class, 'index']);
            Route::post('configs', [FeeConfigController::class, 'save']);
            Route::post('configs/assess', [FeeConfigController::class, 'assess']);

            // Due date + flat fine per sub_head x academic_year
            Route::get('time-configs', [FeeTimeConfigController::class, 'index']);
            Route::post('time-configs', [FeeTimeConfigController::class, 'save']);

            // Per-student waiver assignment
            Route::get('waiver-configs', [FeeWaiverConfigController::class, 'index']);
            Route::post('waiver-configs', [FeeWaiverConfigController::class, 'store']);
            Route::delete('waiver-configs/{id}', [FeeWaiverConfigController::class, 'destroy'])->whereNumber('id');

            // Dues + collection (receipts)
            Route::get('students/{student}/dues', [FeeCollectionController::class, 'dues'])->whereNumber('student');
            Route::get('collections', [FeeCollectionController::class, 'index']);
            Route::post('collections', [FeeCollectionController::class, 'store']);
            Route::get('collections/{id}', [FeeCollectionController::class, 'show'])->whereNumber('id');

            // Reports
            Route::get('reports/{type}', [FeeReportController::class, 'show'])->where('type', 'daily-collection|dues-summary');
        });

        // ---- Accounting module -------------------------------------------------
        Route::prefix('accounting')->middleware('permission:accounting.manage')->group(function () {
            Route::get('ledgers', [LedgerAccountController::class, 'index']);
            Route::post('ledgers', [LedgerAccountController::class, 'store']);
            Route::match(['put', 'patch'], 'ledgers/{id}', [LedgerAccountController::class, 'update'])->whereNumber('id');
            Route::delete('ledgers/{id}', [LedgerAccountController::class, 'destroy'])->whereNumber('id');

            Route::get('vouchers', [VoucherController::class, 'index']);
            Route::post('vouchers', [VoucherController::class, 'store']);
            Route::get('vouchers/{id}', [VoucherController::class, 'show'])->whereNumber('id');

            Route::get('reports/{type}', [AccountingReportController::class, 'show'])->where('type', 'trial-balance|income-statement');
        });

        // ---- Messaging module ---------------------------------------------------
        Route::prefix('messaging')->middleware('permission:messaging.manage')->group(function () {
            Route::get('templates', [TemplateController::class, 'index']);
            Route::post('templates', [TemplateController::class, 'store']);
            Route::match(['put', 'patch'], 'templates/{id}', [TemplateController::class, 'update'])->whereNumber('id');
            Route::delete('templates/{id}', [TemplateController::class, 'destroy'])->whereNumber('id');

            Route::get('contacts', [ContactController::class, 'index']);
            Route::post('contacts', [ContactController::class, 'store']);
            Route::match(['put', 'patch'], 'contacts/{id}', [ContactController::class, 'update'])->whereNumber('id');
            Route::delete('contacts/{id}', [ContactController::class, 'destroy'])->whereNumber('id');

            Route::post('send', [SendController::class, 'send'])->middleware('throttle:sms');
            Route::get('batches', [SendController::class, 'batches']);
            Route::get('batches/{id}', [SendController::class, 'show'])->whereNumber('id');
            Route::get('balance', [SendController::class, 'balance']);
            Route::post('balance/topup', [SendController::class, 'topup']);
        });

        // ---- Credentials module ---------------------------------------------------
        Route::prefix('credentials')->middleware('permission:credentials.manage')->group(function () {
            Route::get('transfer-certificates', [TransferCertificateController::class, 'index']);
            Route::post('transfer-certificates', [TransferCertificateController::class, 'store']);
            Route::get('transfer-certificates/{id}', [TransferCertificateController::class, 'show'])->whereNumber('id');

            Route::get('testimonials', [TestimonialController::class, 'index']);
            Route::post('testimonials', [TestimonialController::class, 'store']);
            Route::get('testimonials/{id}', [TestimonialController::class, 'show'])->whereNumber('id');
            Route::match(['put', 'patch'], 'testimonials/{id}', [TestimonialController::class, 'update'])->whereNumber('id');
            Route::delete('testimonials/{id}', [TestimonialController::class, 'destroy'])->whereNumber('id');

            Route::get('certificates', [CertificateController::class, 'index']);
            Route::post('certificates', [CertificateController::class, 'store']);
            Route::get('certificates/{id}', [CertificateController::class, 'show'])->whereNumber('id');
            Route::match(['put', 'patch'], 'certificates/{id}', [CertificateController::class, 'update'])->whereNumber('id');
            Route::delete('certificates/{id}', [CertificateController::class, 'destroy'])->whereNumber('id');

            Route::get('id-card-templates', [IdCardTemplateController::class, 'index']);
            Route::post('id-card-templates', [IdCardTemplateController::class, 'store']);
            Route::match(['put', 'patch'], 'id-card-templates/{id}', [IdCardTemplateController::class, 'update'])->whereNumber('id');
            Route::delete('id-card-templates/{id}', [IdCardTemplateController::class, 'destroy'])->whereNumber('id');

            Route::post('id-cards/generate', [IdCardController::class, 'generate']);
        });

        // ---- CMS module ---------------------------------------------------
        // Public (rendered-site) read endpoints: any authenticated branch user may read published
        // content — no cms.manage needed. Registered before the admin group so the pages/{path}
        // wildcard here is namespaced under cms/public and can't swallow admin routes.
        Route::prefix('cms/public')->group(function () {
            Route::get('pages', [PublicPageController::class, 'index']);
            Route::get('posts', [PublicPostController::class, 'index']);
            Route::get('posts/{slug}', [PublicPostController::class, 'show']);
            Route::get('taxonomies/{taxonomy}/terms', [PublicTermController::class, 'index']);
            Route::get('terms/{taxonomy}/{slug}', [PublicTermController::class, 'show']);
            Route::get('notices', [PublicNoticeController::class, 'index']);
            Route::get('notices/{slug}', [PublicNoticeController::class, 'show']);
            Route::get('events', [PublicEventController::class, 'index']);
            Route::get('events/{slug}', [PublicEventController::class, 'show']);
            Route::get('menus/{key}', [PublicMenuController::class, 'show']);
            // Registered last: the wildcard swallows any remaining GET path.
            Route::get('pages/{path}', [PublicPageController::class, 'show'])->where('path', '.*');
        });

        // Admin (content management) endpoints — require cms.manage.
        Route::prefix('cms')->middleware('permission:cms.manage')->group(function () {
            // Pages
            Route::get('pages', [PageController::class, 'index']);
            Route::get('pages/meta', [PageController::class, 'meta']);
            Route::post('pages', [PageController::class, 'store']);
            Route::get('pages/{id}', [PageController::class, 'show'])->whereNumber('id');
            Route::match(['put', 'patch'], 'pages/{id}', [PageController::class, 'update'])->whereNumber('id');
            Route::delete('pages/{id}', [PageController::class, 'destroy'])->whereNumber('id');
            Route::post('pages/{id}/restore', [PageController::class, 'restore'])->whereNumber('id');

            // Posts
            Route::get('posts', [PostController::class, 'index']);
            Route::get('posts/meta', [PostController::class, 'meta']);
            Route::post('posts', [PostController::class, 'store']);
            Route::get('posts/{id}', [PostController::class, 'show'])->whereNumber('id');
            Route::match(['put', 'patch'], 'posts/{id}', [PostController::class, 'update'])->whereNumber('id');
            Route::delete('posts/{id}', [PostController::class, 'destroy'])->whereNumber('id');
            Route::post('posts/{id}/restore', [PostController::class, 'restore'])->whereNumber('id');

            // Taxonomies & terms
            Route::get('taxonomies', [TaxonomyController::class, 'index']);
            Route::post('taxonomies', [TaxonomyController::class, 'store']);
            Route::get('taxonomies/{id}', [TaxonomyController::class, 'show'])->whereNumber('id');
            Route::put('taxonomies/{id}', [TaxonomyController::class, 'update'])->whereNumber('id');
            Route::delete('taxonomies/{id}', [TaxonomyController::class, 'destroy'])->whereNumber('id');
            Route::post('terms', [TermController::class, 'store']);
            Route::match(['put', 'patch'], 'terms/{id}', [TermController::class, 'update'])->whereNumber('id');
            Route::delete('terms/{id}', [TermController::class, 'destroy'])->whereNumber('id');

            // Media
            Route::get('media', [AssetController::class, 'index']);
            Route::get('media/picker', [AssetController::class, 'picker']);
            Route::post('media', [AssetController::class, 'store']);
            Route::post('media/bulk-destroy', [AssetController::class, 'bulkDestroy']);
            Route::match(['put', 'patch'], 'media/{id}', [AssetController::class, 'update'])->whereNumber('id');
            Route::delete('media/{id}', [AssetController::class, 'destroy'])->whereNumber('id');
            Route::get('media-folders', [MediaFolderController::class, 'index']);
            Route::post('media-folders', [MediaFolderController::class, 'store']);
            Route::match(['put', 'patch'], 'media-folders/{id}', [MediaFolderController::class, 'update'])->whereNumber('id');
            Route::delete('media-folders/{id}', [MediaFolderController::class, 'destroy'])->whereNumber('id');

            // Menus
            Route::get('menus', [MenuController::class, 'index']);
            Route::post('menus', [MenuController::class, 'store']);
            Route::get('menus/{id}', [MenuController::class, 'show'])->whereNumber('id');
            Route::match(['put', 'patch'], 'menus/{id}', [MenuController::class, 'update'])->whereNumber('id');
            Route::put('menus/{id}/tree', [MenuController::class, 'updateTree'])->whereNumber('id');
            Route::delete('menus/{id}', [MenuController::class, 'destroy'])->whereNumber('id');

            // Notices
            Route::get('notices', [NoticeController::class, 'index']);
            Route::get('notices/meta', [NoticeController::class, 'meta']);
            Route::post('notices', [NoticeController::class, 'store']);
            Route::get('notices/{id}', [NoticeController::class, 'show'])->whereNumber('id');
            Route::match(['put', 'patch'], 'notices/{id}', [NoticeController::class, 'update'])->whereNumber('id');
            Route::delete('notices/{id}', [NoticeController::class, 'destroy'])->whereNumber('id');
            Route::post('notices/{id}/restore', [NoticeController::class, 'restore'])->whereNumber('id');

            // Events
            Route::get('events', [EventController::class, 'index']);
            Route::get('events/meta', [EventController::class, 'meta']);
            Route::post('events', [EventController::class, 'store']);
            Route::get('events/{id}', [EventController::class, 'show'])->whereNumber('id');
            Route::match(['put', 'patch'], 'events/{id}', [EventController::class, 'update'])->whereNumber('id');
            Route::delete('events/{id}', [EventController::class, 'destroy'])->whereNumber('id');
            Route::post('events/{id}/restore', [EventController::class, 'restore'])->whereNumber('id');

            // Redirects
            Route::get('redirects', [RedirectController::class, 'index']);
            Route::post('redirects', [RedirectController::class, 'store']);
            Route::match(['put', 'patch'], 'redirects/{id}', [RedirectController::class, 'update'])->whereNumber('id');
            Route::delete('redirects/{id}', [RedirectController::class, 'destroy'])->whereNumber('id');
        });

        // ---- Reporting subsystem ------------------------------------------------
        // Generic entry point behind a slug whitelist (ReportRegistry), same pattern as the
        // module SetupControllers — add a new report to the registry, not a new route/controller.
        Route::prefix('reports')->middleware(['permission:reports.view', 'throttle:reports'])->group(function () {
            Route::get('{report}/pdf', [ReportController::class, 'pdf'])->where('report', ReportRegistry::keys());
            Route::get('{report}/excel', [ReportController::class, 'excel'])->where('report', ReportRegistry::keys());
            Route::get('artifacts/{id}', [ReportController::class, 'artifactStatus'])->whereNumber('id');
            Route::get('artifacts/{id}/download', [ReportController::class, 'download'])->whereNumber('id')->name('reports.artifacts.download');
        });
    });
});
