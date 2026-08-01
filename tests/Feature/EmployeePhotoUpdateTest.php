<?php

namespace Tests\Feature;

use App\Models\Academic\ClassConfig;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Academic\Subject;
use App\Models\Branch;
use App\Models\Hr\Designation;
use App\Models\Hr\Employee;
use App\Models\Organization;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePhotoUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Designation $designation;

    private Subject $subject;

    private ClassConfig $classConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $org->id, 'name' => 'Main Branch', 'code' => 'MAIN']);
        app(BranchContext::class)->set($this->branch->id);

        $this->designation = Designation::create([
            'branch_id' => $this->branch->id,
            'name' => 'Teacher',
            'serial' => 1,
            'status' => true,
        ]);

        $this->subject = Subject::create([
            'branch_id' => $this->branch->id,
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);

        $class = SchoolClass::create(['branch_id' => $this->branch->id, 'name' => 'Six']);
        $shift = Shift::create(['branch_id' => $this->branch->id, 'name' => 'Morning']);
        $section = Section::create(['branch_id' => $this->branch->id, 'name' => 'A']);
        $this->classConfig = ClassConfig::create([
            'branch_id' => $this->branch->id,
            'class_id' => $class->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
            'serial' => 1,
            'status' => true,
        ]);
    }

    public function test_can_update_employee_photo_path(): void
    {
        $admin = $this->actingAsSuperAdmin($this->branch);
        $this->branch->users()->updateExistingPivot($admin->id, ['is_default' => true]);

        $employee = Employee::create([
            'branch_id' => $this->branch->id,
            'employee_uid' => 'EMP-001',
            'name' => 'John Doe',
            'sex' => 'male',
            'designation_id' => $this->designation->id,
            'joining_date' => '2026-01-01',
            'photo_path' => null,
            'status' => 'active',
        ]);

        $response = $this->putJson("/api/v1/hr/employees/{$employee->id}", [
            'employee_uid' => 'EMP-001',
            'name' => 'John Doe Updated',
            'sex' => 'male',
            'designation_id' => $this->designation->id,
            'joining_date' => '2026-01-01',
            'photo_path' => 'uploads/1/photo/sample_photo.jpg',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.photo_path', 'uploads/1/photo/sample_photo.jpg');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'name' => 'John Doe Updated',
            'photo_path' => 'uploads/1/photo/sample_photo.jpg',
        ]);
    }

    public function test_can_update_employee_with_subject_assignments_repeatedly_without_duplicate_error(): void
    {
        $admin = $this->actingAsSuperAdmin($this->branch);
        $this->branch->users()->updateExistingPivot($admin->id, ['is_default' => true]);

        $employee = Employee::create([
            'branch_id' => $this->branch->id,
            'employee_uid' => 'EMP-002',
            'name' => 'Jane Smith',
            'sex' => 'female',
            'designation_id' => $this->designation->id,
            'joining_date' => '2026-01-01',
            'status' => 'active',
        ]);

        // Initial save with subject assignment
        $response1 = $this->putJson("/api/v1/hr/employees/{$employee->id}", [
            'employee_uid' => 'EMP-002',
            'name' => 'Jane Smith',
            'sex' => 'female',
            'designation_id' => $this->designation->id,
            'joining_date' => '2026-01-01',
            'subject_assignments' => [
                ['subject_id' => $this->subject->id, 'class_config_id' => $this->classConfig->id],
            ],
        ]);
        $response1->assertStatus(200);

        // Second save with same subject assignment (should forceDelete soft-deleted old rows and recreate without 1062 Duplicate entry)
        $response2 = $this->putJson("/api/v1/hr/employees/{$employee->id}", [
            'employee_uid' => 'EMP-002',
            'name' => 'Jane Smith Updated',
            'sex' => 'female',
            'designation_id' => $this->designation->id,
            'joining_date' => '2026-01-01',
            'subject_assignments' => [
                ['subject_id' => $this->subject->id, 'class_config_id' => $this->classConfig->id],
                ['subject_id' => $this->subject->id, 'class_config_id' => $this->classConfig->id], // Duplicate in payload
            ],
        ]);
        $response2->assertStatus(200);
        $response2->assertJsonPath('data.name', 'Jane Smith Updated');
    }
}
