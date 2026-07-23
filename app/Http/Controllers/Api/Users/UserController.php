<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Users are org-scoped (not branch-scoped like tenant data) but the role each
 * user sees here is resolved for the currently active branch, since spatie
 * team_id = branch id and EnsureBranchContext already set it for this request.
 */
class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $orgId = auth()->user()->organization_id;
        $branchId = app(BranchContext::class)->idOrFail();

        $users = User::where('organization_id', $orgId)
            ->whereHas('branches', fn ($q) => $q->where('branches.id', $branchId))
            ->with('branches')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->present($u));

        return ApiResponse::success($users, 'Users retrieved.');
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $temporaryPassword = Str::password(12);

        $user = User::create([
            'organization_id' => auth()->user()->organization_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($temporaryPassword),
            'status' => $data['status'] ?? true,
            'must_reset_password' => true,
        ]);

        $this->syncBranchesAndRole($user, $data['branch_ids'], $data['default_branch_id'] ?? null, $data['role']);

        return ApiResponse::success(
            $this->present($user->fresh('branches')) + ['temporary_password' => $temporaryPassword],
            'User created. Share the temporary password securely — it will not be shown again.',
            status: 201,
        );
    }

    public function show(int $id): JsonResponse
    {
        $user = User::where('organization_id', auth()->user()->organization_id)
            ->with('branches')
            ->findOrFail($id);

        return ApiResponse::success($this->present($user), 'User retrieved.');
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::where('organization_id', auth()->user()->organization_id)->findOrFail($id);
        $data = $request->validated();

        $user->update([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $user->phone,
            'status' => $data['status'] ?? $user->status,
        ]);

        if (isset($data['branch_ids'])) {
            $this->syncBranchesAndRole($user, $data['branch_ids'], $data['default_branch_id'] ?? null, $data['role'] ?? null);
        } elseif (isset($data['role'])) {
            $this->assignRoleInActiveBranch($user, $data['role']);
        }

        return ApiResponse::success($this->present($user->fresh('branches')), 'User updated.');
    }

    /** No hard delete for users — mirrors the no-hard-delete pattern used for Vouchers/TCs (K7). */
    public function deactivate(int $id): JsonResponse
    {
        $user = User::where('organization_id', auth()->user()->organization_id)->findOrFail($id);
        $user->update(['status' => false]);

        return ApiResponse::success($this->present($user->fresh('branches')), 'User deactivated.');
    }

    /** Admin-triggered reset: force the user through the reset-password flow next login. */
    public function forceReset(int $id): JsonResponse
    {
        $user = User::where('organization_id', auth()->user()->organization_id)->findOrFail($id);
        $user->update(['must_reset_password' => true]);
        Password::sendResetLink(['email' => $user->email]);

        return ApiResponse::success(null, 'Password reset requested for this user.');
    }

    private function syncBranchesAndRole(User $user, array $branchIds, ?int $defaultBranchId, ?string $role): void
    {
        $default = $defaultBranchId ?? $branchIds[0];
        $sync = collect($branchIds)->mapWithKeys(fn (int $bId) => [$bId => ['is_default' => $bId === $default]]);
        $user->branches()->sync($sync);

        if ($role !== null) {
            foreach ($branchIds as $branchId) {
                $this->assignRoleInBranch($user, $role, $branchId);
            }
        }
    }

    private function assignRoleInActiveBranch(User $user, string $role): void
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $this->assignRoleInBranch($user, $role, $branchId);
    }

    private function assignRoleInBranch(User $user, string $role, int $branchId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($branchId);
        $user->unsetRelation('roles');
        $user->syncRoles([$role]);
    }

    private function present(User $user): array
    {
        // Resolve the role for the currently active branch (team context already set by EnsureBranchContext).
        $user->unsetRelation('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => (bool) $user->status,
            'must_reset_password' => (bool) $user->must_reset_password,
            'role' => $user->getRoleNames()->first(),
            'branches' => $user->branches->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'is_default' => (bool) $b->pivot->is_default,
            ])->values(),
        ];
    }
}
