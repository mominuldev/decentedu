<?php

namespace Tests\Feature;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Group;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Branch;
use App\Models\Organization;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationSetupTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $org->id, 'name' => 'Main Branch', 'code' => 'MAIN']);
        app(BranchContext::class)->set($this->branch->id);
    }

    private function actingAsBranchUser(): void
    {
        $this->actingAsSuperAdmin($this->branch);
    }

    public function test_can_create_academic_year(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/academic/academic-years', [
            'name' => '2025-2026',
            'name_bn' => '২০২৫-২০২৬',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => true,
            'serial' => 1,
            'status' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => [
                'name' => '2025-2026',
                'name_bn' => '২০২৫-২০২৬',
                'is_current' => true,
            ],
        ]);

        $this->assertDatabaseHas('academic_years', [
            'name' => '2025-2026',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_only_one_current_academic_year_per_branch(): void
    {
        $this->actingAsBranchUser();

        AcademicYear::create([
            'branch_id' => $this->branch->id,
            'name' => '2024-2025',
            'is_current' => true,
        ]);

        $response = $this->postJson('/api/v1/academic/academic-years', [
            'name' => '2025-2026',
            'is_current' => true,
        ]);

        $response->assertStatus(201);

        // Only the new year should be current
        $this->assertDatabaseHas('academic_years', [
            'name' => '2025-2026',
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('academic_years', [
            'name' => '2024-2025',
            'is_current' => false,
        ]);
    }

    public function test_can_list_academic_years(): void
    {
        $this->actingAsBranchUser();

        AcademicYear::factory()->count(3)->create(['branch_id' => $this->branch->id]);

        $response = $this->getJson('/api/v1/academic/academic-years');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_class(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/academic/classes', [
            'name' => 'Six',
            'name_bn' => 'ষষ্ঠ',
            'serial' => 6,
            'status' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => [
                'name' => 'Six',
                'name_bn' => 'ষষ্ঠ',
                'serial' => 6,
            ],
        ]);

        $this->assertDatabaseHas('classes', [
            'name' => 'Six',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_name_must_be_unique_within_branch(): void
    {
        $this->actingAsBranchUser();

        SchoolClass::create([
            'branch_id' => $this->branch->id,
            'name' => 'Six',
        ]);

        $response = $this->postJson('/api/v1/academic/classes', [
            'name' => 'Six',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_class(): void
    {
        $this->actingAsBranchUser();

        $class = SchoolClass::create([
            'branch_id' => $this->branch->id,
            'name' => 'Six',
        ]);

        $response = $this->putJson("/api/v1/academic/classes/{$class->id}", [
            'name' => 'Seven',
            'name_bn' => 'সপ্তম',
            'serial' => 7,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'name' => 'Seven',
                'name_bn' => 'সপ্তম',
                'serial' => 7,
            ],
        ]);

        $this->assertDatabaseHas('classes', [
            'id' => $class->id,
            'name' => 'Seven',
        ]);
    }

    public function test_can_delete_class(): void
    {
        $this->actingAsBranchUser();

        $class = SchoolClass::create([
            'branch_id' => $this->branch->id,
            'name' => 'Six',
        ]);

        $response = $this->deleteJson("/api/v1/academic/classes/{$class->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('classes', [
            'id' => $class->id,
        ]);
    }

    public function test_can_create_subject_with_code(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/academic/subjects', [
            'name' => 'Mathematics',
            'name_bn' => 'গণিত',
            'code' => 'MATH',
            'serial' => 1,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => [
                'name' => 'Mathematics',
                'code' => 'MATH',
            ],
        ]);

        $this->assertDatabaseHas('subjects', [
            'name' => 'Mathematics',
            'code' => 'MATH',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_can_list_shifts(): void
    {
        $this->actingAsBranchUser();

        Shift::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Morning']);
        Shift::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Day']);

        $response = $this->getJson('/api/v1/academic/shifts');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_can_list_sections(): void
    {
        $this->actingAsBranchUser();

        Section::factory()->count(3)->create(['branch_id' => $this->branch->id]);

        $response = $this->getJson('/api/v1/academic/sections');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_list_groups(): void
    {
        $this->actingAsBranchUser();

        Group::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Science']);
        Group::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Arts']);

        $response = $this->getJson('/api/v1/academic/groups');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_search_filters_by_name(): void
    {
        $this->actingAsBranchUser();

        SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Six']);
        SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Seven']);
        SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Eight']);

        $response = $this->getJson('/api/v1/academic/classes?search=Six');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Six');
    }

    public function test_search_filters_by_bangla_name(): void
    {
        $this->actingAsBranchUser();

        SchoolClass::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Six',
            'name_bn' => 'ষষ্ঠ',
        ]);

        $response = $this->getJson('/api/v1/academic/classes?search=ষষ্ঠ');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Six');
    }

    public function test_branch_isolation_for_setup_resources(): void
    {
        $org = Organization::first();
        $otherBranch = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Other Branch',
            'code' => 'OTHER',
        ]);

        // Create data in other branch
        SchoolClass::factory()->create(['branch_id' => $otherBranch->id, 'name' => 'Ten']);

        // Create data in current branch
        SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Six']);

        $this->actingAsBranchUser();

        $response = $this->getJson('/api/v1/academic/classes');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Six');
        $response->assertJsonMissing(['data' => [['name' => 'Ten']]]);
    }
}
