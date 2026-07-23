<?php

namespace Tests;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Feature tests hit permission-gated routes now that permission: middleware is wired —
     * Super Admin bypasses every check via Gate::before, so tests that aren't specifically
     * about authorization can use this instead of asserting against a fine-grained permission set.
     */
    protected function actingAsSuperAdmin(Branch $branch): User
    {
        $user = User::factory()->create();
        $branch->users()->attach($user->id);

        app(PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $user->assignRole('Super Admin');

        $this->actingAs($user);

        return $user;
    }
}
