<?php

use App\Http\Controllers\Api\Academic\ClassConfigController;
use App\Http\Controllers\Api\Academic\SetupController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\BranchController;
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
    });
});
