<?php

namespace Tests\Feature;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Admissions\AdmissionApplication;
use App\Models\Admissions\AdmissionYear;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\Students\Enrollment;
use App\Models\Students\Student;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionTest extends TestCase
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

    private function makeYear(): int
    {
        return $this->postJson('/api/v1/admissions/years', ['title' => 'Admission 2026'])
            ->assertCreated()->json('data.id');
    }

    private function applicationPayload(array $overrides = []): array
    {
        return array_merge([
            // Only mint a fresh drive when the caller hasn't supplied one (the API enforces a
            // unique title per branch, so calling makeYear() unconditionally would collide).
            'admission_year_id' => $overrides['admission_year_id'] ?? $this->makeYear(),
            'class_config_id' => $this->classConfig->id,
            'name' => 'Rifat Hasan',
            'sex' => 'male',
            'fathers_name' => 'Kamal Hasan',
            'mothers_name' => 'Rina Begum',
            'score' => 88.5,
        ], $overrides);
    }

    public function test_application_number_is_auto_generated_when_omitted(): void
    {
        $this->actingAsSuperAdmin($this->branch);

        $this->postJson('/api/v1/admissions/applications', $this->applicationPayload())
            ->assertCreated()
            ->assertJsonPath('data.application_no', 'APP-0001')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_status_can_be_moved_through_the_pipeline(): void
    {
        $this->actingAsSuperAdmin($this->branch);
        $id = $this->postJson('/api/v1/admissions/applications', $this->applicationPayload())
            ->json('data.id');

        $this->postJson("/api/v1/admissions/applications/{$id}/status", ['status' => 'selected'])
            ->assertOk()
            ->assertJsonPath('data.status', 'selected');
    }

    public function test_converting_a_selected_applicant_creates_a_student_and_enrollment(): void
    {
        $this->actingAsSuperAdmin($this->branch);
        $id = $this->postJson('/api/v1/admissions/applications', $this->applicationPayload(['status' => 'selected']))
            ->json('data.id');

        $response = $this->postJson("/api/v1/admissions/applications/{$id}/convert", [
            'student_uid' => '2026-0001',
            'academic_year_id' => $this->year->id,
            'roll' => '5',
        ])->assertCreated();

        $studentId = $response->json('data.student_id');

        $this->assertDatabaseHas('students', ['id' => $studentId, 'student_uid' => '2026-0001', 'name' => 'Rifat Hasan']);
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $studentId,
            'class_config_id' => $this->classConfig->id,
            'roll' => '5',
            'is_current' => true,
        ]);
        $this->assertSame('admitted', AdmissionApplication::find($id)->status);
        $this->assertSame(1, Student::count());
        $this->assertSame(1, Enrollment::count());
    }

    public function test_an_already_admitted_application_cannot_be_converted_again(): void
    {
        $this->actingAsSuperAdmin($this->branch);
        $id = $this->postJson('/api/v1/admissions/applications', $this->applicationPayload(['status' => 'selected']))
            ->json('data.id');

        $payload = ['student_uid' => '2026-0001', 'academic_year_id' => $this->year->id, 'roll' => '5'];
        $this->postJson("/api/v1/admissions/applications/{$id}/convert", $payload)->assertCreated();

        $this->postJson("/api/v1/admissions/applications/{$id}/convert", $payload + ['student_uid' => '2026-0002'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'ALREADY_ADMITTED');
    }

    public function test_a_rejected_application_cannot_be_converted(): void
    {
        $this->actingAsSuperAdmin($this->branch);
        $id = $this->postJson('/api/v1/admissions/applications', $this->applicationPayload(['status' => 'rejected']))
            ->json('data.id');

        $this->postJson("/api/v1/admissions/applications/{$id}/convert", [
            'student_uid' => '2026-0001',
            'academic_year_id' => $this->year->id,
            'roll' => '5',
        ])->assertStatus(422)->assertJsonPath('error_code', 'APPLICATION_REJECTED');
    }

    public function test_stats_reflect_application_statuses(): void
    {
        $this->actingAsSuperAdmin($this->branch);
        $yearId = $this->makeYear();

        foreach (['pending', 'pending', 'selected', 'rejected'] as $i => $status) {
            $this->postJson('/api/v1/admissions/applications', $this->applicationPayload([
                'admission_year_id' => $yearId,
                'application_no' => 'APP-100'.$i,
                'status' => $status,
            ]))->assertCreated();
        }

        $this->getJson("/api/v1/admissions/applications/stats?admission_year_id={$yearId}")
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.pending', 2)
            ->assertJsonPath('data.selected', 1)
            ->assertJsonPath('data.rejected', 1);
    }

    public function test_applications_are_scoped_to_the_active_branch(): void
    {
        // Application in another branch must not leak into this branch's list.
        $otherOrg = Organization::create(['name' => 'Other Org', 'slug' => 'other-org']);
        $otherBranch = Branch::create(['organization_id' => $otherOrg->id, 'name' => 'Other', 'code' => 'OTH']);
        app(BranchContext::class)->set($otherBranch->id);
        AdmissionApplication::create([
            'branch_id' => $otherBranch->id,
            'admission_year_id' => AdmissionYear::create(['branch_id' => $otherBranch->id, 'title' => 'Other Drive'])->id,
            'class_config_id' => ClassConfig::create([
                'branch_id' => $otherBranch->id,
                'class_id' => SchoolClass::create(['branch_id' => $otherBranch->id, 'name' => 'Six'])->id,
                'shift_id' => Shift::create(['branch_id' => $otherBranch->id, 'name' => 'Morning'])->id,
                'section_id' => Section::create(['branch_id' => $otherBranch->id, 'name' => 'A'])->id,
                'serial' => 1, 'status' => true,
            ])->id,
            'application_no' => 'APP-9999',
            'name' => 'Leaky Applicant',
            'sex' => 'male',
            'fathers_name' => 'X',
            'mothers_name' => 'Y',
        ]);
        app(BranchContext::class)->set($this->branch->id);

        $this->actingAsSuperAdmin($this->branch);
        $this->postJson('/api/v1/admissions/applications', $this->applicationPayload())->assertCreated();

        $list = $this->getJson('/api/v1/admissions/applications')->assertOk()->json('data');
        $this->assertCount(1, $list);
        $this->assertSame('Rifat Hasan', $list[0]['name']);
    }
}
