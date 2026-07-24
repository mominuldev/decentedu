<?php

namespace Tests\Feature;

use App\Jobs\SendAbsenteeAttendanceNotice;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Attendance\StudentAttendance;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\Students\Enrollment;
use App\Models\Students\Student;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class StudentAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private ClassConfig $classConfig;

    private Student $studentA;

    private Student $studentB;

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
        $year = AcademicYear::create(['branch_id' => $this->branch->id, 'name' => '2026', 'is_current' => true]);

        $this->studentA = $this->makeStudent('A');
        $this->studentB = $this->makeStudent('B');

        foreach ([$this->studentA, $this->studentB] as $i => $student) {
            Enrollment::create([
                'branch_id' => $this->branch->id,
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_config_id' => $this->classConfig->id,
                'roll' => (string) ($i + 1),
                'is_current' => true,
            ]);
        }
    }

    private function makeStudent(string $suffix, ?int $branchId = null): Student
    {
        return Student::create([
            'branch_id' => $branchId ?? $this->branch->id,
            'student_uid' => 'STU-'.$suffix,
            'name' => 'Student '.$suffix,
            'sex' => 'male',
            'fathers_name' => 'Father '.$suffix,
            'mothers_name' => 'Mother '.$suffix,
            'status' => 'active',
        ]);
    }

    private function actingAsBranchUser(): void
    {
        $this->actingAsSuperAdmin($this->branch);
    }

    public function test_roster_defaults_to_present_when_unmarked(): void
    {
        $this->actingAsBranchUser();

        $response = $this->getJson("/api/v1/attendance/students?class_config_id={$this->classConfig->id}&date=2026-07-20");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.status', 'present');
        $response->assertJsonPath('data.0.attendance_id', null);
    }

    public function test_can_take_attendance_for_a_class(): void
    {
        Bus::fake();
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/attendance/students/take', [
            'class_config_id' => $this->classConfig->id,
            'date' => '2026-07-20',
            'entries' => [
                ['student_id' => $this->studentA->id, 'status' => 'present'],
                ['student_id' => $this->studentB->id, 'status' => 'absent', 'remarks' => 'Sick'],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $this->studentA->id, 'date' => '2026-07-20', 'status' => 'present',
        ]);
        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $this->studentB->id, 'date' => '2026-07-20', 'status' => 'absent', 'remarks' => 'Sick',
        ]);
    }

    public function test_marking_a_student_absent_dispatches_the_absentee_notice_job(): void
    {
        Bus::fake();
        $this->actingAsBranchUser();

        $this->postJson('/api/v1/attendance/students/take', [
            'class_config_id' => $this->classConfig->id,
            'date' => '2026-07-20',
            'entries' => [
                ['student_id' => $this->studentA->id, 'status' => 'absent'],
            ],
        ])->assertStatus(200);

        Bus::assertDispatched(SendAbsenteeAttendanceNotice::class);
    }

    public function test_marking_present_does_not_dispatch_the_absentee_notice_job(): void
    {
        Bus::fake();
        $this->actingAsBranchUser();

        $this->postJson('/api/v1/attendance/students/take', [
            'class_config_id' => $this->classConfig->id,
            'date' => '2026-07-20',
            'entries' => [
                ['student_id' => $this->studentA->id, 'status' => 'present'],
            ],
        ])->assertStatus(200);

        Bus::assertNotDispatched(SendAbsenteeAttendanceNotice::class);
    }

    public function test_retaking_attendance_updates_the_existing_record_instead_of_duplicating(): void
    {
        Bus::fake();
        $this->actingAsBranchUser();

        $this->postJson('/api/v1/attendance/students/take', [
            'class_config_id' => $this->classConfig->id,
            'date' => '2026-07-20',
            'entries' => [['student_id' => $this->studentA->id, 'status' => 'present']],
        ])->assertStatus(200);

        $this->postJson('/api/v1/attendance/students/take', [
            'class_config_id' => $this->classConfig->id,
            'date' => '2026-07-20',
            'entries' => [['student_id' => $this->studentA->id, 'status' => 'late']],
        ])->assertStatus(200);

        $this->assertSame(1, StudentAttendance::where('student_id', $this->studentA->id)->where('date', '2026-07-20')->count());
        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $this->studentA->id, 'date' => '2026-07-20', 'status' => 'late',
        ]);
    }

    public function test_can_update_a_single_attendance_record(): void
    {
        $this->actingAsBranchUser();

        $attendance = StudentAttendance::create([
            'branch_id' => $this->branch->id,
            'student_id' => $this->studentA->id,
            'class_config_id' => $this->classConfig->id,
            'date' => '2026-07-20',
            'status' => 'present',
        ]);

        $response = $this->putJson("/api/v1/attendance/students/{$attendance->id}", ['status' => 'late', 'remarks' => 'Traffic']);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'late');
        $this->assertDatabaseHas('student_attendances', ['id' => $attendance->id, 'status' => 'late', 'remarks' => 'Traffic']);
    }

    public function test_report_totals_attendance_by_student_over_a_range(): void
    {
        $this->actingAsBranchUser();

        foreach (['2026-07-19' => 'present', '2026-07-20' => 'absent', '2026-07-21' => 'present'] as $date => $status) {
            StudentAttendance::create([
                'branch_id' => $this->branch->id,
                'student_id' => $this->studentA->id,
                'class_config_id' => $this->classConfig->id,
                'date' => $date,
                'status' => $status,
            ]);
        }

        $response = $this->getJson("/api/v1/attendance/students/report?class_config_id={$this->classConfig->id}&from=2026-07-19&to=2026-07-21");

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('student_id', $this->studentA->id);
        $this->assertSame(2, $row['present']);
        $this->assertSame(1, $row['absent']);
        $this->assertSame(3, $row['total_days']);
    }

    public function test_rejects_a_student_not_belonging_to_the_branch(): void
    {
        $this->actingAsBranchUser();

        $org = Organization::first();
        $otherBranch = Branch::create(['organization_id' => $org->id, 'name' => 'Other', 'code' => 'OTHER']);
        $foreignStudent = $this->makeStudent('Foreign', $otherBranch->id);

        $response = $this->postJson('/api/v1/attendance/students/take', [
            'class_config_id' => $this->classConfig->id,
            'date' => '2026-07-20',
            'entries' => [['student_id' => $foreignStudent->id, 'status' => 'present']],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['entries.0.student_id']);
    }
}
