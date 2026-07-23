<?php

namespace Tests\Feature;

use App\Models\Academic\ClassConfig;
use App\Models\Academic\Group;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Branch;
use App\Models\Organization;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassConfigTest extends TestCase
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

    private function createRequiredEntities(): array
    {
        $class = SchoolClass::create(['branch_id' => $this->branch->id, 'name' => 'Six']);
        $shift = Shift::create(['branch_id' => $this->branch->id, 'name' => 'Morning']);
        $section = Section::create(['branch_id' => $this->branch->id, 'name' => 'A']);
        $group = Group::create(['branch_id' => $this->branch->id, 'name' => 'Science']);

        return [$class, $shift, $section, $group];
    }

    public function test_can_create_class_config(): void
    {
        $this->actingAsBranchUser();

        [$class, $shift, $section] = $this->createRequiredEntities();

        $response = $this->postJson('/api/v1/academic/class-configs', [
            'class_id' => $class->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
            'serial' => 1,
            'status' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => [
                'class_name' => 'Six',
                'shift_name' => 'Morning',
                'section_name' => 'A',
                'label' => 'Six · A · Morning',
            ],
        ]);

        $this->assertDatabaseHas('class_configs', [
            'class_id' => $class->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
        ]);
    }

    public function test_cannot_duplicate_class_config_combination(): void
    {
        $this->actingAsBranchUser();

        [$class, $shift, $section] = $this->createRequiredEntities();

        ClassConfig::create([
            'branch_id' => $this->branch->id,
            'class_id' => $class->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
        ]);

        $response = $this->postJson('/api/v1/academic/class-configs', [
            'class_id' => $class->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'This class · section · shift combination already exists.',
        ]);
    }

    public function test_can_list_class_configs(): void
    {
        $this->actingAsBranchUser();

        [$class, $shift, $section] = $this->createRequiredEntities();

        ClassConfig::factory()->count(3)->create([
            'branch_id' => $this->branch->id,
            'class_id' => $class->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
        ]);

        $response = $this->getJson('/api/v1/academic/class-configs');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_filter_class_configs_by_class(): void
    {
        $this->actingAsBranchUser();

        [$class1, $shift, $section] = $this->createRequiredEntities();
        $class2 = SchoolClass::create(['branch_id' => $this->branch->id, 'name' => 'Seven']);

        ClassConfig::factory()->count(2)->create([
            'branch_id' => $this->branch->id,
            'class_id' => $class1->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
        ]);

        ClassConfig::factory()->count(3)->create([
            'branch_id' => $this->branch->id,
            'class_id' => $class2->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
        ]);

        $response = $this->getJson("/api/v1/academic/class-configs?class_id={$class1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $config) {
            $this->assertEquals($class1->id, $config['class_id']);
        }
    }

    public function test_can_update_class_config(): void
    {
        $this->actingAsBranchUser();

        [$class, $shift, $section] = $this->createRequiredEntities();

        $config = ClassConfig::create([
            'branch_id' => $this->branch->id,
            'class_id' => $class->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
            'serial' => 1,
        ]);

        $response = $this->putJson("/api/v1/academic/class-configs/{$config->id}", [
            'serial' => 5,
            'status' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'serial' => 5,
                'status' => false,
            ],
        ]);

        $this->assertDatabaseHas('class_configs', [
            'id' => $config->id,
            'serial' => 5,
            'status' => false,
        ]);
    }

    public function test_can_delete_class_config(): void
    {
        $this->actingAsBranchUser();

        [$class, $shift, $section] = $this->createRequiredEntities();

        $config = ClassConfig::create([
            'branch_id' => $this->branch->id,
            'class_id' => $class->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
        ]);

        $response = $this->deleteJson("/api/v1/academic/class-configs/{$config->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('class_configs', [
            'id' => $config->id,
        ]);
    }

    public function test_can_get_class_config_options(): void
    {
        $this->actingAsBranchUser();

        [$class, $shift, $section, $group] = $this->createRequiredEntities();

        // Create some inactive items to ensure they're filtered out
        SchoolClass::create(['branch_id' => $this->branch->id, 'name' => 'Inactive', 'status' => false]);
        Shift::create(['branch_id' => $this->branch->id, 'name' => 'Inactive Shift', 'status' => false]);
        Section::create(['branch_id' => $this->branch->id, 'name' => 'Z', 'status' => false]);
        Group::create(['branch_id' => $this->branch->id, 'name' => 'Inactive Group', 'status' => false]);

        $response = $this->getJson('/api/v1/academic/class-configs/options');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'classes',
                'shifts',
                'sections',
                'groups',
            ],
        ]);

        // Only active items should be included
        $response->assertJsonCount(1, 'data.classes');
        $response->assertJsonCount(1, 'data.shifts');
        $response->assertJsonCount(1, 'data.sections');
        $response->assertJsonCount(1, 'data.groups');

        $response->assertJsonPath('data.classes.0.name', 'Six');
        $response->assertJsonMissing(['data' => ['classes' => [['name' => 'Inactive']]]]);
    }

    public function test_class_config_label_formatting(): void
    {
        $this->actingAsBranchUser();

        [$class, $shift, $section] = $this->createRequiredEntities();

        $config = ClassConfig::create([
            'branch_id' => $this->branch->id,
            'class_id' => $class->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
        ]);

        $response = $this->getJson("/api/v1/academic/class-configs/{$config->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.label', 'Six · A · Morning');
    }

    public function test_branch_isolation_for_class_configs(): void
    {
        $org = Organization::first();
        $otherBranch = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Other Branch',
            'code' => 'OTHER',
        ]);

        // Create entities in other branch
        [$otherClass, $otherShift, $otherSection] = [
            SchoolClass::create(['branch_id' => $otherBranch->id, 'name' => 'Ten']),
            Shift::create(['branch_id' => $otherBranch->id, 'name' => 'Evening']),
            Section::create(['branch_id' => $otherBranch->id, 'name' => 'B']),
        ];

        ClassConfig::create([
            'branch_id' => $otherBranch->id,
            'class_id' => $otherClass->id,
            'shift_id' => $otherShift->id,
            'section_id' => $otherSection->id,
        ]);

        // Create entities and config in current branch
        [$class, $shift, $section] = $this->createRequiredEntities();

        ClassConfig::create([
            'branch_id' => $this->branch->id,
            'class_id' => $class->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
        ]);

        $this->actingAsBranchUser();

        $response = $this->getJson('/api/v1/academic/class-configs');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.class_name', 'Six');
    }

    public function test_validates_foreign_keys_exist_in_branch(): void
    {
        $this->actingAsBranchUser();

        $org = Organization::first();
        $otherBranch = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Other Branch',
            'code' => 'OTHER',
        ]);

        $foreignClass = SchoolClass::create(['branch_id' => $otherBranch->id, 'name' => 'Ten']);
        [$shift, $section] = $this->createRequiredEntities();

        $response = $this->postJson('/api/v1/academic/class-configs', [
            'class_id' => $foreignClass->id, // Foreign class from different branch
            'shift_id' => $shift->id,
            'section_id' => $section->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['class_id']);
    }

    public function test_class_config_includes_relationships(): void
    {
        $this->actingAsBranchUser();

        [$class, $shift, $section] = $this->createRequiredEntities();

        $config = ClassConfig::create([
            'branch_id' => $this->branch->id,
            'class_id' => $class->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
        ]);

        $response = $this->getJson("/api/v1/academic/class-configs/{$config->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'class_name' => 'Six',
                'shift_name' => 'Morning',
                'section_name' => 'A',
            ],
        ]);
    }
}