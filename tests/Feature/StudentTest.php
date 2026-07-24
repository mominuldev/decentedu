<?php

namespace Tests\Feature;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\Students\Student;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicYear $year;

    private ClassConfig $classConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $org->id, 'name' => 'Main Branch', 'code' => 'MAIN']);
        app(BranchContext::class)->set($this->branch->id);

        $this->year = AcademicYear::create([
            'branch_id' => $this->branch->id,
            'name' => '2025-2026',
            'is_current' => true,
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

    private function studentPayload(array $overrides = []): array
    {
        return array_merge([
            'student_uid' => 'STU-001',
            'name' => 'Jamal Uddin',
            'sex' => 'male',
            'fathers_name' => 'Kamal Uddin',
            'mothers_name' => 'Rina Begum',
            'academic_year_id' => $this->year->id,
            'class_config_id' => $this->classConfig->id,
            'roll' => '1',
        ], $overrides);
    }

    public function test_can_create_a_student_with_an_initial_enrollment(): void
    {
        $this->actingAsSuperAdmin($this->branch);

        $response = $this->postJson('/api/v1/students', $this->studentPayload());

        $response->assertStatus(201);
        $this->assertDatabaseHas('students', ['student_uid' => 'STU-001', 'branch_id' => $this->branch->id]);
        $this->assertDatabaseHas('student_enrollments', [
            'class_config_id' => $this->classConfig->id,
            'roll' => '1',
            'is_current' => true,
        ]);
    }

    public function test_can_update_a_student(): void
    {
        $this->actingAsSuperAdmin($this->branch);
        $this->postJson('/api/v1/students', $this->studentPayload());
        $student = Student::where('student_uid', 'STU-001')->firstOrFail();

        $response = $this->putJson("/api/v1/students/{$student->id}", ['name' => 'Jamal Ahmed Uddin']);

        $response->assertOk();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'name' => 'Jamal Ahmed Uddin']);
    }

    public function test_bulk_register_creates_all_valid_students(): void
    {
        $this->actingAsSuperAdmin($this->branch);

        $response = $this->postJson('/api/v1/students/bulk-register', [
            'academic_year_id' => $this->year->id,
            'class_config_id' => $this->classConfig->id,
            'students' => [
                ['student_uid' => 'STU-100', 'name' => 'Student A', 'sex' => 'male', 'fathers_name' => 'F A', 'mothers_name' => 'M A', 'roll' => '10'],
                ['student_uid' => 'STU-101', 'name' => 'Student B', 'sex' => 'female', 'fathers_name' => 'F B', 'mothers_name' => 'M B', 'roll' => '11'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.summary.created_count', 2);
        $response->assertJsonPath('data.summary.failed_count', 0);
        $this->assertDatabaseHas('students', ['student_uid' => 'STU-100']);
        $this->assertDatabaseHas('students', ['student_uid' => 'STU-101']);
    }

    public function test_bulk_register_reports_partial_failure_without_losing_the_valid_rows(): void
    {
        $this->actingAsSuperAdmin($this->branch);
        // Pre-existing row so the second bulk entry collides on the unique roll/class_config/year index.
        $this->postJson('/api/v1/students', $this->studentPayload(['student_uid' => 'STU-200', 'roll' => '20']));

        $response = $this->postJson('/api/v1/students/bulk-register', [
            'academic_year_id' => $this->year->id,
            'class_config_id' => $this->classConfig->id,
            'students' => [
                ['student_uid' => 'STU-201', 'name' => 'Student Good', 'sex' => 'male', 'fathers_name' => 'F', 'mothers_name' => 'M', 'roll' => '21'],
                // Roll 20 in the same class_config + academic year already belongs to STU-200 above.
                ['student_uid' => 'STU-202', 'name' => 'Student Bad', 'sex' => 'male', 'fathers_name' => 'F', 'mothers_name' => 'M', 'roll' => '20'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.summary.created_count', 1);
        $response->assertJsonPath('data.summary.failed_count', 1);
        $response->assertJsonPath('data.failed.0.student_uid', 'STU-202');
        $this->assertDatabaseHas('students', ['student_uid' => 'STU-201']);
        // The failed row's Student was created before the Enrollment insert failed — this is a
        // pre-existing gap (no transaction wraps the two inserts) worth knowing about, not fixing here.
        $this->assertDatabaseHas('students', ['student_uid' => 'STU-202']);
    }

    public function test_bulk_register_rejects_more_than_one_hundred_students(): void
    {
        $this->actingAsSuperAdmin($this->branch);

        $students = [];
        for ($i = 0; $i < 101; $i++) {
            $students[] = [
                'student_uid' => "STU-BULK-{$i}",
                'name' => "Student {$i}",
                'sex' => 'male',
                'fathers_name' => 'F',
                'mothers_name' => 'M',
                'roll' => (string) $i,
            ];
        }

        $response = $this->postJson('/api/v1/students/bulk-register', [
            'academic_year_id' => $this->year->id,
            'class_config_id' => $this->classConfig->id,
            'students' => $students,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('students');
    }

    public function test_branch_isolation_for_students(): void
    {
        $org = Organization::where('slug', 'test-org')->first();
        $otherBranch = Branch::create(['organization_id' => $org->id, 'name' => 'Other Branch', 'code' => 'OTHER']);

        $this->actingAsSuperAdmin($this->branch);
        $this->postJson('/api/v1/students', $this->studentPayload());
        $student = Student::where('student_uid', 'STU-001')->firstOrFail();

        app(BranchContext::class)->set($otherBranch->id);
        $this->actingAsSuperAdmin($otherBranch);

        $this->getJson("/api/v1/students/{$student->id}")->assertStatus(404);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->postJson('/api/v1/students', $this->studentPayload());

        $response->assertStatus(401);
    }

    public function test_a_user_without_the_students_manage_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->branch->users()->attach($user->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        Role::firstOrCreate(['name' => 'Teacher', 'guard_name' => 'web']);
        $user->assignRole('Teacher');
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/students', $this->studentPayload());

        $response->assertStatus(403);
    }
}
