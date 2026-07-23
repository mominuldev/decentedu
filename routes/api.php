<?php

use App\Http\Controllers\Api\Academic\ClassConfigController;
use App\Http\Controllers\Api\Academic\SetupController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\BranchController;
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
    });
});
