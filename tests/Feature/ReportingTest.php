<?php

namespace Tests\Feature;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Academic\Subject;
use App\Models\Accounting\LedgerAccount;
use App\Models\Branch;
use App\Models\Examinations\Exam;
use App\Models\Examinations\StudentExamResult;
use App\Models\Examinations\StudentExamSummary;
use App\Models\Fees\FeeHead;
use App\Models\Fees\FeeSubHead;
use App\Models\Fees\StudentFee;
use App\Models\Organization;
use App\Models\Reporting\ReportArtifact;
use App\Models\Students\Enrollment;
use App\Models\Students\Student;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private ClassConfig $classConfig;

    private Student $student;

    private Exam $exam;

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
        $this->exam = Exam::create(['branch_id' => $this->branch->id, 'name' => 'Final', 'type' => 'final']);
        $subject = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Math']);

        $this->student = Student::create([
            'branch_id' => $this->branch->id, 'student_uid' => 'STU-A', 'name' => 'Student A',
            'sex' => 'male', 'fathers_name' => 'Father A', 'mothers_name' => 'Mother A', 'status' => 'active',
        ]);

        StudentExamResult::create([
            'branch_id' => $this->branch->id, 'student_id' => $this->student->id, 'exam_id' => $this->exam->id,
            'subject_id' => $subject->id, 'class_config_id' => $this->classConfig->id,
            'total_marks' => 100, 'obtained_marks' => 80, 'is_pass' => true, 'is_absent' => false,
        ]);
        StudentExamSummary::create([
            'branch_id' => $this->branch->id, 'student_id' => $this->student->id, 'exam_id' => $this->exam->id,
            'class_config_id' => $this->classConfig->id, 'total_marks' => 100, 'total_obtained' => 80,
            'gpa' => 4.5, 'is_pass' => true, 'failed_subjects_count' => 0, 'class_position' => 1, 'section_position' => 1,
        ]);
    }

    private function actingAsBranchUser(): void
    {
        $user = User::factory()->create();
        $this->branch->users()->attach($user->id);
        $this->actingAs($user);
    }

    public function test_marksheet_pdf_renders_for_a_class_config_and_exam(): void
    {
        $this->actingAsBranchUser();

        $response = $this->get('/api/v1/reports/marksheet/pdf?'.http_build_query([
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_marksheet_excel_export_downloads(): void
    {
        $this->actingAsBranchUser();

        $response = $this->get('/api/v1/reports/marksheet/excel?'.http_build_query([
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheet', $response->headers->get('content-type'));
    }

    public function test_unknown_report_key_is_rejected(): void
    {
        $this->actingAsBranchUser();

        $this->get('/api/v1/reports/not-a-real-report/pdf')->assertStatus(404);
    }

    public function test_report_with_no_pdf_output_returns_422(): void
    {
        $this->actingAsBranchUser();

        // tabulation-sheet has a dynamic subject-column matrix — Excel-only definitions
        // export null headings, this one has no defined excel output.
        $response = $this->get('/api/v1/reports/tabulation-sheet/excel?'.http_build_query([
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
        ]));

        $response->assertStatus(422);
    }

    public function test_class_config_from_another_branch_fails_validation(): void
    {
        $this->actingAsBranchUser();
        $otherOrg = Organization::create(['name' => 'Other Org', 'slug' => 'other-org']);
        $otherBranch = Branch::create(['organization_id' => $otherOrg->id, 'name' => 'Other Branch', 'code' => 'OTHER']);
        app(BranchContext::class)->set($otherBranch->id);
        $otherClass = SchoolClass::create(['branch_id' => $otherBranch->id, 'name' => 'Seven']);
        $otherShift = Shift::create(['branch_id' => $otherBranch->id, 'name' => 'Morning']);
        $otherSection = Section::create(['branch_id' => $otherBranch->id, 'name' => 'B']);
        $otherClassConfig = ClassConfig::create([
            'branch_id' => $otherBranch->id, 'class_id' => $otherClass->id, 'shift_id' => $otherShift->id, 'section_id' => $otherSection->id,
        ]);
        app(BranchContext::class)->set($this->branch->id);

        $response = $this->get('/api/v1/reports/marksheet/pdf?'.http_build_query([
            'class_config_id' => $otherClassConfig->id,
            'exam_id' => $this->exam->id,
        ]));

        $response->assertStatus(422);
    }

    public function test_trial_balance_pdf_renders(): void
    {
        $this->actingAsBranchUser();
        LedgerAccount::create(['branch_id' => $this->branch->id, 'name' => 'Cash', 'code' => 'CASH', 'type' => 'asset']);

        $response = $this->get('/api/v1/reports/trial-balance/pdf');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_fee_dues_summary_is_queued_and_downloadable_once_ready(): void
    {
        Storage::fake('local');
        $this->actingAsBranchUser();

        $year = AcademicYear::create(['branch_id' => $this->branch->id, 'name' => '2026', 'is_current' => true]);
        Enrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $this->student->id, 'academic_year_id' => $year->id,
            'class_config_id' => $this->classConfig->id, 'roll' => '1', 'is_current' => true,
        ]);
        $head = FeeHead::create(['branch_id' => $this->branch->id, 'name' => 'Tuition Fee']);
        $subHead = FeeSubHead::create(['branch_id' => $this->branch->id, 'fee_head_id' => $head->id, 'name' => 'Monthly Tuition']);
        StudentFee::create([
            'branch_id' => $this->branch->id, 'student_id' => $this->student->id, 'class_config_id' => $this->classConfig->id,
            'fee_sub_head_id' => $subHead->id, 'academic_year_id' => $year->id, 'payable_amount' => 1000, 'status' => 'due',
        ]);

        // Sync queue driver runs the job inline, so the artifact is already ready by the time we poll.
        $queued = $this->get('/api/v1/reports/fee-dues-summary/pdf?academic_year_id='.$year->id);
        $queued->assertStatus(202);
        $artifactId = $queued->json('data.artifact_id');
        $this->assertNotNull($artifactId);

        $status = $this->getJson("/api/v1/reports/artifacts/{$artifactId}");
        $status->assertStatus(200)->assertJsonPath('data.status', 'ready');

        $artifact = ReportArtifact::find($artifactId);
        Storage::disk('local')->assertExists($artifact->file_path);

        $this->get("/api/v1/reports/artifacts/{$artifactId}/download")->assertStatus(200);
    }

    public function test_download_before_ready_is_rejected(): void
    {
        $this->actingAsBranchUser();
        $artifact = ReportArtifact::create([
            'branch_id' => $this->branch->id, 'report_key' => 'fee-dues-summary', 'format' => 'pdf', 'status' => 'pending',
        ]);

        $this->get("/api/v1/reports/artifacts/{$artifact->id}/download")->assertStatus(409);
    }
}
