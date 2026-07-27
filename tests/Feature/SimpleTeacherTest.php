<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleTeacherTest extends TestCase
{
    use RefreshDatabase;

    public function test_teachers_endpoint_works_with_seeded_data()
    {
        // Seed the database
        $this->seed();

        // Get the demo user and branch
        $user = User::where('email', 'demo@decentedu.test')->first();
        $branch = Branch::where('code', 'BR01')->first();

        $this->assertNotNull($user, 'Demo user should exist');
        $this->assertNotNull($branch, 'IT branch should exist');

        // Set the branch context
        app(BranchContext::class)->set($branch->id);

        // Act as the demo user
        $this->actingAs($user);

        // Test the teachers endpoint
        $response = $this->getJson('/api/v1/hr/teachers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Teachers retrieved successfully.');
    }

    public function test_teachers_available_endpoint_works()
    {
        // Seed the database
        $this->seed();

        // Get the demo user and branch
        $user = User::where('email', 'demo@decentedu.test')->first();
        $branch = Branch::where('code', 'BR01')->first();

        app(BranchContext::class)->set($branch->id);
        $this->actingAs($user);

        // Test the available teachers endpoint
        $response = $this->getJson('/api/v1/hr/teachers/available');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Available teachers retrieved successfully.');
    }
}