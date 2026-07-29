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

    /** List all branches in the current user's organization. */
    public function listBranches(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $branches = Branch::where('organization_id', $orgId)
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $b) => $this->presentBranch($b));

        return ApiResponse::success($branches, 'Branches retrieved.');
    }

    /** Create a new branch inside the current user's organization (Super Admin only). */
    public function createBranch(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('branches')->where('organization_id', $orgId)],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ]);

        $branch = Branch::create([
            'organization_id' => $orgId,
            'name' => $data['name'],
            'name_bn' => $data['name_bn'] ?? null,
            'code' => $data['code'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'logo_path' => $data['logo_path'] ?? null,
            'status' => $data['status'] ?? true,
        ]);

        return ApiResponse::success($this->presentBranch($branch), 'Branch created successfully.', status: 201);
    }

    /** Update any branch by ID within the current user's organization. */
    public function updateBranch(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $branch = Branch::where('organization_id', $orgId)->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('branches')->ignore($branch->id)->where('organization_id', $orgId)],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ]);

        $branch->update([
            'name' => $data['name'],
            'name_bn' => $data['name_bn'] ?? null,
            'code' => $data['code'] ?? $branch->code,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'logo_path' => $data['logo_path'] ?? null,
            'status' => $data['status'] ?? $branch->status,
        ]);

        return ApiResponse::success($this->presentBranch($branch), 'Branch updated successfully.');
    }

    /** Get system diagnostics and runtime statistics. */
    public function getSystemSettings(): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $branch = Branch::find($branchId);

        return ApiResponse::success([
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

    /** Soft-delete a branch by ID (Super Admin only). Cannot delete the currently active branch. */
    public function deleteBranch(Request $request, int $id): JsonResponse
    {
        $orgId = $request->user()->organization_id;
        $branch = Branch::where('organization_id', $orgId)->findOrFail($id);

        // Safety: prevent deleting the branch the user is currently operating in.
        $activeBranchId = $request->session()->get('active_branch_id');
        if ($activeBranchId && (int) $activeBranchId === $branch->id) {
            return ApiResponse::error('You cannot delete your currently active branch. Switch to another branch first.', 'ACTIVE_BRANCH', 422);
        }

        $branch->delete();

        return ApiResponse::success(null, 'Branch deleted successfully.');
    }

    /** Serialize a Branch model to a consistent API shape. */
    private function presentBranch(Branch $branch): array
    {
        return [
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
        ];
    }
}
