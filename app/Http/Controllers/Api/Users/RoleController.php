<?php

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Role -> permission grants are global (role_has_permissions has no team column;
 * only the per-user, per-branch *assignment* of a role is team-scoped). The fixed
 * role set (Super Admin, Admin, Accountant, Exam Controller, Teacher) is seeded —
 * this controller only edits which permissions each role carries.
 */
class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions:id,name')->orderBy('name')->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'permissions' => $r->permissions->pluck('name')->values(),
            ]);

        return ApiResponse::success($roles, 'Roles retrieved.');
    }

    public function permissions(): JsonResponse
    {
        return ApiResponse::success(
            Permission::orderBy('name')->pluck('name'),
            'Permissions retrieved.',
        );
    }

    public function updatePermissions(Request $request, int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if ($role->name === 'Super Admin') {
            return ApiResponse::error('Super Admin permissions are fixed (full access via Gate::before).', 'IMMUTABLE_ROLE', 422);
        }

        $role->syncPermissions($data['permissions']);

        return ApiResponse::success([
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('name')->values(),
        ], 'Role permissions updated.');
    }
}
