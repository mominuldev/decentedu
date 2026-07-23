<?php

use App\Http\Controllers\Api\Academic\ClassConfigController;
use App\Http\Controllers\Api\Academic\SetupController;
use App\Http\Controllers\Api\Accounting\AccountingReportController;
use App\Http\Controllers\Api\Accounting\LedgerAccountController;
use App\Http\Controllers\Api\Accounting\VoucherController;
use App\Http\Controllers\Api\Attendance\DeviceController;
use App\Http\Controllers\Api\Attendance\DeviceMapController;
use App\Http\Controllers\Api\Attendance\EmployeeAttendanceController;
use App\Http\Controllers\Api\Attendance\HolidayController;
use App\Http\Controllers\Api\Attendance\PunchController;
use App\Http\Controllers\Api\Attendance\StudentAttendanceController;
use App\Http\Controllers\Api\Attendance\TimeConfigController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\BranchController;
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
use App\Http\Controllers\Api\Routines\ClassRoutineController;
use App\Http\Controllers\Api\Routines\PeriodController;
use App\Http\Controllers\Api\Students\StudentController;
use App\Http\Controllers\Api\Hr\EmployeeController;
use App\Http\Controllers\Api\Hr\SetupController as HrSetupController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

// Slugs handled by the generic setup controller.
$setupSlugs = 'academic-years|classes|shifts|sections|groups|categories|subjects';

Route::prefix('v1')->group(function () use ($setupSlugs) {
    // Public
    Route::post('auth/login', [AuthController::class, 'login']);

    // Authenticated (Sanctum SPA cookie session) + active-branch context.
    Route::middleware(['auth:sanctum', 'branch'])->group(function () use ($setupSlugs) {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('branch/switch', [BranchController::class, 'switch']);

        Route::get('dashboard', function () {
            return ApiResponse::success([
                'total_students' => 1284,
                'present_today' => 1173,
                'absent_today' => 111,
                'collection_today' => 184500,
            ], 'Dashboard summary');
        });

        // ---- Academic module -------------------------------------------------
        Route::prefix('academic')->group(function () use ($setupSlugs) {
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
        Route::prefix('students')->group(function () {
            Route::get('/', [StudentController::class, 'index']);
            Route::post('/', [StudentController::class, 'store']);
            Route::post('bulk-register', [StudentController::class, 'bulkRegister']);
            Route::post('migrate', [StudentController::class, 'migrate']);
            Route::get('{id}', [StudentController::class, 'show'])->whereNumber('id');
            Route::match(['put', 'patch'], '{id}', [StudentController::class, 'update'])->whereNumber('id');
            Route::delete('{id}', [StudentController::class, 'destroy'])->whereNumber('id');
        });

        // ---- HR module -------------------------------------------------------
        $hrSetupSlugs = 'designations|hr-sections';

        Route::prefix('hr')->group(function () use ($hrSetupSlugs) {
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
        });

        // ---- Routines module --------------------------------------------------
        Route::prefix('routines')->group(function () {
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
        Route::prefix('attendance')->group(function () {
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

        Route::prefix('examinations')->group(function () use ($examSetupSlugs) {
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

        Route::prefix('fees')->group(function () use ($feeSetupSlugs) {
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
        Route::prefix('accounting')->group(function () {
            Route::get('ledgers', [LedgerAccountController::class, 'index']);
            Route::post('ledgers', [LedgerAccountController::class, 'store']);
            Route::match(['put', 'patch'], 'ledgers/{id}', [LedgerAccountController::class, 'update'])->whereNumber('id');
            Route::delete('ledgers/{id}', [LedgerAccountController::class, 'destroy'])->whereNumber('id');

            Route::get('vouchers', [VoucherController::class, 'index']);
            Route::post('vouchers', [VoucherController::class, 'store']);
            Route::get('vouchers/{id}', [VoucherController::class, 'show'])->whereNumber('id');

            Route::get('reports/{type}', [AccountingReportController::class, 'show'])->where('type', 'trial-balance|income-statement');
        });
    });
});
