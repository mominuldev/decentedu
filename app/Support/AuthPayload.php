<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

/**
 * Assembles the session payload the SPA needs after login / on /me:
 * the user, their accessible branches, the active branch, role and permissions.
 */
class AuthPayload
{
    public static function for(User $user, Request $request): array
    {
        $branches = $user->branches()->where('branches.status', true)->get();
        $activeId = $request->session()->get('active_branch_id');
        $active = $branches->firstWhere('id', $activeId) ?? $branches->first();

        // Roles/permissions are branch-scoped (spatie teams: team_id = branch id).
        app(PermissionRegistrar::class)->setPermissionsTeamId($active?->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $roleName = $user->getRoleNames()->first();
        $isSuper = $roleName === 'Super Admin';

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_path' => $user->avatar_path,
                'role' => $roleName,
                'is_super_admin' => $isSuper,
                'must_reset_password' => $user->must_reset_password,
            ],
            'organization' => $user->organization
                ? ['id' => $user->organization->id, 'name' => $user->organization->name]
                : null,
            'active_branch' => $active ? self::branch($active) : null,
            'branches' => $branches->map(fn (Branch $b) => self::branch($b))->values(),
            'permissions' => $isSuper ? ['*'] : $user->getAllPermissions()->pluck('name')->values(),
        ];
    }

    private static function branch(Branch $b): array
    {
        return [
            'id' => $b->id,
            'name' => $b->name,
            'name_bn' => $b->name_bn,
            'code' => $b->code,
            'is_default' => (bool) ($b->pivot->is_default ?? false),
        ];
    }
}
