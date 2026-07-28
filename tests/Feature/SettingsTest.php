<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Branch $branch;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $this->org->id, 'name' => 'Main Campus', 'code' => 'MAIN']);
        app(BranchContext::class)->set($this->branch->id);

        $this->withHeader('Referer', 'http://localhost:8000');
        $this->withCredentials();
        $this->withSession(['active_branch_id' => $this->branch->id]);

        $this->user = $this->actingAsSuperAdmin($this->branch);
    }

    public function test_can_get_branch_settings(): void
    {
        $response = $this->getJson('/api/v1/settings/branch');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Main Campus')
            ->assertJsonPath('data.settings.currency_symbol', '৳');
    }

    public function test_can_update_branch_settings(): void
    {
        $response = $this->putJson('/api/v1/settings/branch', [
            'name' => 'Updated Main Campus',
            'name_bn' => 'আপডেট মেইন ক্যাম্পাস',
            'code' => 'MAIN',
            'phone' => '+880 1711-000000',
            'settings' => [
                'timezone' => 'Asia/Dhaka',
                'currency_symbol' => '৳',
                'date_format' => 'd/m/Y',
                'sms_sender_id' => 'DecentTest',
                'header_notice' => 'Welcome to updated campus!',
                'auto_student_id' => false,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Main Campus')
            ->assertJsonPath('data.settings.sms_sender_id', 'DecentTest');

        $this->assertDatabaseHas('branches', [
            'id' => $this->branch->id,
            'name' => 'Updated Main Campus',
            'phone' => '+880 1711-000000',
        ]);
    }

    public function test_can_get_system_settings(): void
    {
        $response = $this->getJson('/api/v1/settings/system');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.active_branch.name', 'Main Campus');
    }

    public function test_can_update_user_profile(): void
    {
        $response = $this->putJson('/api/v1/settings/profile', [
            'name' => 'Jane Doe',
            'email' => 'jane@test.com',
            'phone' => '+880 1800-111222',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Jane Doe');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Jane Doe',
            'email' => 'jane@test.com',
        ]);
    }

    public function test_can_list_branches(): void
    {
        $this->user->update(['organization_id' => $this->org->id]);

        // Create a second branch in the same org
        Branch::create([
            'organization_id' => $this->org->id,
            'name' => 'Second Campus',
            'code' => 'SEC',
            'status' => true,
        ]);

        $response = $this->getJson('/api/v1/settings/branches');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_branch(): void
    {
        $this->user->update(['organization_id' => $this->org->id]);

        $response = $this->postJson('/api/v1/settings/branches', [
            'name' => 'New Campus',
            'name_bn' => 'নতুন ক্যাম্পাস',
            'code' => 'NEW',
            'phone' => '+880 1700-999999',
            'email' => 'new@campus.edu',
            'address' => 'Road 1, Block A, Dhaka',
            'status' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Campus')
            ->assertJsonPath('data.code', 'NEW');

        $this->assertDatabaseHas('branches', [
            'organization_id' => $this->org->id,
            'name' => 'New Campus',
            'code' => 'NEW',
        ]);
    }

    public function test_can_update_branch_by_id(): void
    {
        $this->user->update(['organization_id' => $this->org->id]);

        $response = $this->putJson("/api/v1/settings/branches/{$this->branch->id}", [
            'name' => 'Updated Campus',
            'code' => 'UPD',
            'phone' => '+880 1700-123456',
            'status' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Campus')
            ->assertJsonPath('data.code', 'UPD');

        $this->assertDatabaseHas('branches', [
            'id' => $this->branch->id,
            'name' => 'Updated Campus',
        ]);
    }
}
