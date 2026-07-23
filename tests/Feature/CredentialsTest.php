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
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CredentialsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private ClassConfig $classConfig;
    private AcademicYear $year;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $org->id, 'name' => 'Main Branch', 'code' => 'MAIN']);
        app(BranchContext::class)->set($this->branch->id);

        $class = SchoolClass::create(['branch_id' => $this->branch->id, 'name' => 'Six']);
        $shift = Shift::create(['branch_id' => $this->branch->id, 'name' => 'Morning']);
        $section = Section::create(['branch_id' => $this->branch->id, 'name' => 'A']);
        $this->classConfig = ClassConfig::create([
            'branch_id' => $this->branch->id, 'class_id' => $class->id, 'shift_id' => $shift->id, 'section_id' => $section->id,
        ]);
        $this->year = AcademicYear::create(['branch_id' => $this->branch->id, 'name' => '2026', 'is_current' => true]);

        $this->student = Student::create([
            'branch_id' => $this->branch->id, 'student_uid' => 'STU-A', 'name' => 'Student A',
            'sex' => 'male', 'fathers_name' => 'Father A', 'mothers_name' => 'Mother A', 'status' => 'active',
        ]);
    }

    private function actingAsBranchUser(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->branch->users()->attach($user->id);
        $this->actingAs($user);
    }

    public function test_issuing_a_transfer_certificate_marks_the_student_transferred(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/credentials/transfer-certificates', [
            'student_id' => $this->student->id,
            'issue_date' => now()->toDateString(),
            'reason_for_leaving' => 'Relocation',
            'academic_year_id' => $this->year->id,
            'class_config_id' => $this->classConfig->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('transfer_certificates', ['student_id' => $this->student->id]);

        $this->student->refresh();
        $this->assertSame('transferred', $this->student->status);
    }

    public function test_transfer_certificates_have_no_update_or_destroy_route(): void
    {
        $this->actingAsBranchUser();
        $tc = $this->postJson('/api/v1/credentials/transfer-certificates', [
            'student_id' => $this->student->id,
            'issue_date' => now()->toDateString(),
            'academic_year_id' => $this->year->id,
            'class_config_id' => $this->classConfig->id,
        ])->json('data.id');

        $this->putJson("/api/v1/credentials/transfer-certificates/{$tc}", ['remarks' => 'edited'])->assertStatus(405);
        $this->deleteJson("/api/v1/credentials/transfer-certificates/{$tc}")->assertStatus(405);
    }

    public function test_testimonial_can_be_issued_and_deleted(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/credentials/testimonials', [
            'student_id' => $this->student->id,
            'issue_date' => now()->toDateString(),
            'academic_year_id' => $this->year->id,
            'class_config_id' => $this->classConfig->id,
        ]);
        $response->assertStatus(201);
        $id = $response->json('data.id');

        $this->deleteJson("/api/v1/credentials/testimonials/{$id}")->assertStatus(200);
        $this->assertSoftDeleted('testimonials', ['id' => $id]);
    }

    public function test_branch_isolation_for_certificates(): void
    {
        $org = Organization::first();
        $otherBranch = Branch::create(['organization_id' => $org->id, 'name' => 'Other Branch', 'code' => 'OTHER']);
        $otherStudent = Student::create([
            'branch_id' => $otherBranch->id, 'student_uid' => 'STU-B', 'name' => 'Student B',
            'sex' => 'female', 'fathers_name' => 'Father B', 'mothers_name' => 'Mother B', 'status' => 'active',
        ]);

        app(BranchContext::class)->set($otherBranch->id);
        \App\Models\Students\Certificate::create([
            'branch_id' => $otherBranch->id, 'student_id' => $otherStudent->id, 'certificate_type' => 'academic',
            'certificate_number' => 'CERT-OTHER-1', 'issue_date' => now(),
        ]);

        app(BranchContext::class)->set($this->branch->id);
        $this->assertSame(0, \App\Models\Students\Certificate::count(), 'Branch A must not see Branch B certificates.');
    }
}
