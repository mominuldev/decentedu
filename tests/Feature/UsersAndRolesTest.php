<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UsersAndRolesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $this->org->id, 'name' => 'Main Branch', 'code' => 'MAIN']);
        app(BranchContext::class)->set($this->branch->id);
    }

    private function actingAsAdmin(): User
    {
        return $this->actingAsSuperAdmin($this->branch);
    }

    private function makeUserWithRole(string $roleName, array $permissions = []): User
    {
        // actingAsSuperAdmin()'s user has a null organization_id, and UserController scopes
        // update/deactivate/force-reset to auth()->user()->organization_id — match it here.
        $user = User::factory()->create(['organization_id' => null]);
        $this->branch->users()->attach($user->id, ['is_default' => true]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role->syncPermissions($permissions);
        $user->assignRole($role);

        return $user;
    }

    public function test_any_authenticated_user_can_list_users(): void
    {
        $user = $this->makeUserWithRole('Teacher');
        $this->actingAs($user);

        $this->getJson('/api/v1/users')->assertOk();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/users')->assertStatus(401);
    }

    public function test_a_user_without_users_manage_cannot_create_users(): void
    {
        $user = $this->makeUserWithRole('Teacher');
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New Teacher',
            'email' => 'new-teacher@example.com',
            'branch_ids' => [$this->branch->id],
            'role' => 'Teacher',
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_create_a_user_with_a_temporary_password(): void
    {
        $this->actingAsAdmin();
        Role::firstOrCreate(['name' => 'Teacher', 'guard_name' => 'web']);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New Teacher',
            'email' => 'new-teacher@example.com',
            'branch_ids' => [$this->branch->id],
            'role' => 'Teacher',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.role', 'Teacher');
        $this->assertNotEmpty($response->json('data.temporary_password'));
        $this->assertDatabaseHas('users', ['email' => 'new-teacher@example.com', 'must_reset_password' => true]);

        $created = User::where('email', 'new-teacher@example.com')->firstOrFail();
        $this->assertTrue($created->branches()->where('branches.id', $this->branch->id)->exists());
    }

    public function test_creating_a_user_rejects_an_unknown_role(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New Teacher',
            'email' => 'new-teacher@example.com',
            'branch_ids' => [$this->branch->id],
            'role' => 'Not A Real Role',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('role');
    }

    public function test_creating_a_user_rejects_a_duplicate_email(): void
    {
        $this->actingAsAdmin();
        Role::firstOrCreate(['name' => 'Teacher', 'guard_name' => 'web']);
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New Teacher',
            'email' => 'taken@example.com',
            'branch_ids' => [$this->branch->id],
            'role' => 'Teacher',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_can_update_a_users_role_in_the_active_branch(): void
    {
        $this->actingAsAdmin();
        Role::firstOrCreate(['name' => 'Teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        $user = $this->makeUserWithRole('Teacher');

        $response = $this->putJson("/api/v1/users/{$user->id}", ['role' => 'Accountant']);

        $response->assertOk();
        $response->assertJsonPath('data.role', 'Accountant');
    }

    public function test_can_deactivate_a_user(): void
    {
        $this->actingAsAdmin();
        $user = $this->makeUserWithRole('Teacher');

        $response = $this->postJson("/api/v1/users/{$user->id}/deactivate");

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => false]);
    }

    public function test_force_reset_flags_the_user_and_sends_a_reset_link(): void
    {
        Notification::fake();
        $this->actingAsAdmin();
        $user = $this->makeUserWithRole('Teacher');

        $response = $this->postJson("/api/v1/users/{$user->id}/force-reset");

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'must_reset_password' => true]);
    }

    public function test_lists_roles_with_their_permissions(): void
    {
        $this->actingAsAdmin();
        $role = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'fees.manage', 'guard_name' => 'web']);
        $role->syncPermissions(['fees.manage']);

        $response = $this->getJson('/api/v1/roles');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Accountant', 'permissions' => ['fees.manage']]);
    }

    public function test_lists_all_available_permissions(): void
    {
        $this->actingAsAdmin();
        Permission::firstOrCreate(['name' => 'fees.manage', 'guard_name' => 'web']);

        $response = $this->getJson('/api/v1/roles/permissions');

        $response->assertOk();
        $response->assertJsonFragment(['fees.manage']);
    }

    public function test_can_update_a_roles_permissions(): void
    {
        $this->actingAsAdmin();
        $role = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'fees.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'accounting.manage', 'guard_name' => 'web']);

        $response = $this->putJson("/api/v1/roles/{$role->id}/permissions", [
            'permissions' => ['fees.manage', 'accounting.manage'],
        ]);

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            ['fees.manage', 'accounting.manage'],
            $role->fresh()->permissions->pluck('name')->all(),
        );
    }

    public function test_super_admin_permissions_are_immutable(): void
    {
        $this->actingAsAdmin();
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'fees.manage', 'guard_name' => 'web']);

        $response = $this->putJson("/api/v1/roles/{$superAdmin->id}/permissions", [
            'permissions' => ['fees.manage'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'IMMUTABLE_ROLE');
    }

    public function test_updating_role_permissions_requires_users_manage(): void
    {
        $user = $this->makeUserWithRole('Teacher');
        $this->actingAs($user);
        $role = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);

        $response = $this->putJson("/api/v1/roles/{$role->id}/permissions", [
            'permissions' => [],
        ]);

        $response->assertStatus(403);
    }
}
