<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Hr\Employee;
use App\Models\Students\Student;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AuthPayload;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /** Get current branch details and configuration settings. */
    public function getBranchSettings(): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $branch = Branch::findOrFail($branchId);

        $defaultSettings = [
            'timezone' => config('app.timezone', 'Asia/Dhaka'),
            'currency_symbol' => '৳',
            'date_format' => 'Y-m-d',
            'sms_sender_id' => 'DecentEdu',
            'header_notice' => null,
            'auto_student_id' => true,
        ];

        $settings = array_merge($defaultSettings, $branch->settings ?? []);

        return ApiResponse::success([
            'id' => $branch->id,
            'organization_id' => $branch->organization_id,
            'name' => $branch->name,
            'name_bn' => $branch->name_bn,
            'code' => $branch->code,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'email' => $branch->email,
            'logo_path' => $branch->logo_path,
            'status' => $branch->status,
            'settings' => $settings,
        ], 'Branch settings retrieved.');
    }

    /** Update current branch details and configuration settings. */
    public function updateBranchSettings(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $branch = Branch::findOrFail($branchId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('branches')->ignore($branch->id)->where('organization_id', $branch->organization_id)],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
            'settings.timezone' => ['nullable', 'string', 'max:100'],
            'settings.currency_symbol' => ['nullable', 'string', 'max:10'],
            'settings.date_format' => ['nullable', 'string', 'max:20'],
            'settings.sms_sender_id' => ['nullable', 'string', 'max:50'],
            'settings.header_notice' => ['nullable', 'string', 'max:500'],
            'settings.auto_student_id' => ['nullable', 'boolean'],
        ]);

        $currentSettings = $branch->settings ?? [];
        $newSettings = array_merge($currentSettings, $data['settings'] ?? []);

        $branch->update([
            'name' => $data['name'],
            'name_bn' => $data['name_bn'] ?? null,
            'code' => $data['code'] ?? $branch->code,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'logo_path' => $data['logo_path'] ?? null,
            'settings' => $newSettings,
        ]);

        return ApiResponse::success([
            'id' => $branch->id,
            'organization_id' => $branch->organization_id,
            'name' => $branch->name,
            'name_bn' => $branch->name_bn,
            'code' => $branch->code,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'email' => $branch->email,
            'logo_path' => $branch->logo_path,
            'status' => $branch->status,
            'settings' => $branch->settings,
        ], 'Branch settings updated successfully.');
    }

    /** Get system diagnostics and runtime statistics. */
    public function getSystemSettings(): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $branch = Branch::find($branchId);

        return ApiResponse::success([
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'db_driver' => config('database.default'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'environment' => app()->environment(),
            'server_time' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'active_branch' => [
                'id' => $branch?->id,
                'name' => $branch?->name,
                'code' => $branch?->code,
            ],
            'counts' => [
                'students' => Student::count(),
                'employees' => Employee::count(),
                'users' => User::count(),
            ],
        ], 'System info retrieved.');
    }

    /** Update current user's profile information. */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'avatar_path' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update($data);

        return ApiResponse::success(AuthPayload::for($user, $request), 'Profile updated successfully.');
    }
}
