<?php

namespace Tests\Feature;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Academic\Subject;
use App\Models\Branch;
use App\Models\Examinations\Exam;
use App\Models\Examinations\ExamConfig;
use App\Models\Examinations\Grade;
use App\Models\Examinations\MarkConfig;
use App\Models\Examinations\ShortCode;
use App\Models\Examinations\StudentExamResult;
use App\Models\Examinations\StudentExamSummary;
use App\Models\Examinations\StudentFourthSubject;
use App\Models\Organization;
use App\Models\Students\Enrollment;
use App\Models\Students\Student;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExaminationResultsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private SchoolClass $class;

    private ClassConfig $classConfig;

    private Exam $exam;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $org->id, 'name' => 'Main Branch', 'code' => 'MAIN']);
        app(BranchContext::class)->set($this->branch->id);

        $this->class = SchoolClass::create(['branch_id' => $this->branch->id, 'name' => 'Nine']);
        $shift = Shift::create(['branch_id' => $this->branch->id, 'name' => 'Morning']);
        $section = Section::create(['branch_id' => $this->branch->id, 'name' => 'A']);
        $this->classConfig = ClassConfig::create([
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'shift_id' => $shift->id,
            'section_id' => $section->id,
            'serial' => 1,
            'status' => true,
        ]);

        $this->exam = Exam::create(['branch_id' => $this->branch->id, 'name' => 'Half Yearly', 'type' => 'monthly']);
        $this->subject = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Math']);

        // Standard Bangladesh-board grade scale for this class.
        $scale = [
            ['name' => 'A+', 'grade_point' => 5.00, 'mark_from' => 80, 'mark_to' => 100],
            ['name' => 'A', 'grade_point' => 4.00, 'mark_from' => 70, 'mark_to' => 79.99],
            ['name' => 'A-', 'grade_point' => 3.50, 'mark_from' => 60, 'mark_to' => 69.99],
            ['name' => 'B', 'grade_point' => 3.00, 'mark_from' => 50, 'mark_to' => 59.99],
            ['name' => 'C', 'grade_point' => 2.00, 'mark_from' => 40, 'mark_to' => 49.99],
            ['name' => 'D', 'grade_point' => 1.00, 'mark_from' => 33, 'mark_to' => 39.99],
            ['name' => 'F', 'grade_point' => 0.00, 'mark_from' => 0, 'mark_to' => 32.99],
        ];
        foreach ($scale as $g) {
            Grade::create(array_merge(['branch_id' => $this->branch->id, 'class_id' => $this->class->id, 'status' => true], $g));
        }
    }

    private function actingAsBranchUser(): void
    {
        $this->actingAsSuperAdmin($this->branch);
    }

    /** Written (70/pass23) + MCQ (30/pass10) — the seeded pattern from ExaminationSeeder. */
    private function makeMarkConfigs(Subject $subject, ?Exam $exam = null): array
    {
        $exam ??= $this->exam;
        $written = ShortCode::firstOrCreate(['branch_id' => $this->branch->id, 'name' => 'Written']);
        $mcq = ShortCode::firstOrCreate(['branch_id' => $this->branch->id, 'name' => 'MCQ']);

        $writtenConfig = MarkConfig::create([
            'branch_id' => $this->branch->id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'short_code_id' => $written->id,
            'total_marks' => 70,
            'pass_mark' => 23,
            'status' => true,
        ]);
        $mcqConfig = MarkConfig::create([
            'branch_id' => $this->branch->id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'short_code_id' => $mcq->id,
            'total_marks' => 30,
            'pass_mark' => 10,
            'status' => true,
        ]);

        return [$writtenConfig, $mcqConfig];
    }

    private function makeStudent(string $uid, string $roll = '1'): Student
    {
        $student = Student::create([
            'branch_id' => $this->branch->id,
            'student_uid' => $uid,
            'name' => "Student {$uid}",
            'sex' => 'male',
            'fathers_name' => 'Father',
            'mothers_name' => 'Mother',
            'status' => 'active',
        ]);

        Enrollment::create([
            'branch_id' => $this->branch->id,
            'student_id' => $student->id,
            'academic_year_id' => AcademicYear::firstOrCreate(
                ['branch_id' => $this->branch->id, 'name' => '2025-2026'],
                ['is_current' => true],
            )->id,
            'class_config_id' => $this->classConfig->id,
            'roll' => $roll,
            'is_current' => true,
            'enrolled_at' => now(),
        ]);

        return $student;
    }

    private function enterMarks(Student $student, MarkConfig $writtenConfig, MarkConfig $mcqConfig, ?float $written, ?float $mcq, bool $absent = false): void
    {
        $this->postJson('/api/v1/examinations/marks', [
            'exam_id' => $this->exam->id,
            'entries' => [
                [
                    'student_id' => $student->id,
                    'is_absent' => $absent,
                    'marks' => [
                        ['mark_config_id' => $writtenConfig->id, 'obtained' => $written],
                        ['mark_config_id' => $mcqConfig->id, 'obtained' => $mcq],
                    ],
                ],
            ],
        ])->assertOk();
    }

    // ---- Marks input ------------------------------------------------------

    public function test_absent_flag_forces_obtained_to_null_even_if_a_value_was_submitted(): void
    {
        $this->actingAsBranchUser();
        [$written, $mcq] = $this->makeMarkConfigs($this->subject);
        $student = $this->makeStudent('STU-1');

        $this->enterMarks($student, $written, $mcq, 65, 25, absent: true);

        $this->assertDatabaseHas('marks', ['student_id' => $student->id, 'mark_config_id' => $written->id, 'obtained' => null, 'is_absent' => true]);
        $this->assertDatabaseHas('marks', ['student_id' => $student->id, 'mark_config_id' => $mcq->id, 'obtained' => null, 'is_absent' => true]);
    }

    public function test_marks_endpoint_accepts_an_absent_entry_with_no_components_touched(): void
    {
        $this->actingAsBranchUser();
        $this->makeMarkConfigs($this->subject);
        $student = $this->makeStudent('STU-1');

        // No mark_config entries submitted -> nothing to key an "absent" Mark row off of, so no
        // rows are written — the important thing is this no longer 422s the whole batch.
        $response = $this->postJson('/api/v1/examinations/marks', [
            'exam_id' => $this->exam->id,
            'entries' => [
                ['student_id' => $student->id, 'is_absent' => true, 'marks' => []],
            ],
        ]);

        $response->assertOk();
    }

    public function test_saving_a_batch_does_not_fail_for_students_nobody_has_entered_marks_for_yet(): void
    {
        $this->actingAsBranchUser();
        [$written, $mcq] = $this->makeMarkConfigs($this->subject);
        $touched = $this->makeStudent('STU-1', '1');
        $untouched = $this->makeStudent('STU-2', '2');

        // Only the "touched" student has marks; the "untouched" one is submitted with an empty
        // marks array (e.g. the whole enrolled roster is posted each save, per the grid UI) —
        // this must not 422 the entire batch.
        $response = $this->postJson('/api/v1/examinations/marks', [
            'exam_id' => $this->exam->id,
            'entries' => [
                ['student_id' => $touched->id, 'marks' => [
                    ['mark_config_id' => $written->id, 'obtained' => 56],
                    ['mark_config_id' => $mcq->id, 'obtained' => 24],
                ]],
                ['student_id' => $untouched->id, 'marks' => []],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('marks', ['student_id' => $touched->id, 'mark_config_id' => $written->id, 'obtained' => 56]);
        $this->assertDatabaseMissing('marks', ['student_id' => $untouched->id]);
    }

    public function test_obtained_mark_exceeding_the_component_total_is_rejected(): void
    {
        $this->actingAsBranchUser();
        [$written, $mcq] = $this->makeMarkConfigs($this->subject);
        $student = $this->makeStudent('STU-1');

        $response = $this->postJson('/api/v1/examinations/marks', [
            'exam_id' => $this->exam->id,
            'entries' => [
                ['student_id' => $student->id, 'marks' => [
                    ['mark_config_id' => $written->id, 'obtained' => 71],
                    ['mark_config_id' => $mcq->id, 'obtained' => 25],
                ]],
            ],
        ]);

        $response->assertStatus(422);
    }

    // ---- Grade scale --------------------------------------------------------

    public function test_grade_lookup_resolves_the_band_containing_the_percentage(): void
    {
        $this->assertSame('A+', Grade::forPercentage($this->class->id, 85)->name);
        $this->assertSame('A', Grade::forPercentage($this->class->id, 70)->name);
        $this->assertSame('F', Grade::forPercentage($this->class->id, 10)->name);
        $this->assertNull(Grade::forPercentage($this->class->id, 150));
    }

    public function test_creating_a_grade_with_an_overlapping_range_is_rejected(): void
    {
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/examinations/grades', [
            'class_id' => $this->class->id,
            'name' => 'X',
            'grade_point' => 2.5,
            'mark_from' => 45,
            'mark_to' => 55,
        ]);

        $response->assertStatus(422);
    }

    // ---- General process (component roll-up, pass/fail, grade) --------------

    public function test_general_process_sums_components_and_grades_the_subject(): void
    {
        $this->actingAsBranchUser();
        [$written, $mcq] = $this->makeMarkConfigs($this->subject);
        $student = $this->makeStudent('STU-1');
        $this->enterMarks($student, $written, $mcq, 56, 24); // 80/100 = 80% -> A+

        $response = $this->postJson('/api/v1/examinations/results/general-process', [
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.subject_results_processed', 1);

        $result = StudentExamResult::where('student_id', $student->id)->firstOrFail();
        $this->assertEquals(100, (float) $result->total_marks);
        $this->assertEquals(80, (float) $result->obtained_marks);
        $this->assertTrue((bool) $result->is_pass);
        $this->assertFalse((bool) $result->is_absent);
        $this->assertSame('A+', $result->grade->name);
    }

    public function test_general_process_fails_a_student_who_is_short_of_the_combined_pass_mark(): void
    {
        $this->actingAsBranchUser();
        [$written, $mcq] = $this->makeMarkConfigs($this->subject);
        $student = $this->makeStudent('STU-1');
        // Combined pass mark is 33 (23+10); 20+10=30 obtained, under the combined threshold.
        $this->enterMarks($student, $written, $mcq, 20, 10);

        $this->postJson('/api/v1/examinations/results/general-process', [
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
        ])->assertOk();

        $result = StudentExamResult::where('student_id', $student->id)->firstOrFail();
        $this->assertFalse((bool) $result->is_pass);
    }

    public function test_general_process_zeroes_obtained_marks_and_forces_fail_for_an_absent_student(): void
    {
        $this->actingAsBranchUser();
        [$written, $mcq] = $this->makeMarkConfigs($this->subject);
        $student = $this->makeStudent('STU-1');
        $this->enterMarks($student, $written, $mcq, null, null, absent: true);

        $this->postJson('/api/v1/examinations/results/general-process', [
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
        ])->assertOk();

        $result = StudentExamResult::where('student_id', $student->id)->firstOrFail();
        $this->assertEquals(0, (float) $result->obtained_marks);
        $this->assertTrue((bool) $result->is_absent);
        $this->assertFalse((bool) $result->is_pass);
    }

    public function test_general_process_skips_a_student_with_no_marks_entered_at_all(): void
    {
        $this->actingAsBranchUser();
        $this->makeMarkConfigs($this->subject);
        $this->makeStudent('STU-1'); // enrolled, but no Mark rows created for them

        $response = $this->postJson('/api/v1/examinations/results/general-process', [
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.subject_results_processed', 0);
        $this->assertDatabaseCount('student_exam_results', 0);
    }

    public function test_reprocessing_the_same_exam_upserts_rather_than_duplicates(): void
    {
        $this->actingAsBranchUser();
        [$written, $mcq] = $this->makeMarkConfigs($this->subject);
        $student = $this->makeStudent('STU-1');
        $this->enterMarks($student, $written, $mcq, 56, 24);

        $process = fn () => $this->postJson('/api/v1/examinations/results/general-process', [
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
        ])->assertOk();

        $process();
        $process();

        $this->assertDatabaseCount('student_exam_results', 1);
    }

    // ---- Final process (equal-weight average of component exams) ------------

    public function test_final_process_averages_two_component_exams_equally(): void
    {
        $this->actingAsBranchUser();
        $examOne = Exam::create(['branch_id' => $this->branch->id, 'name' => 'Monthly 1', 'type' => 'monthly']);
        $examTwo = Exam::create(['branch_id' => $this->branch->id, 'name' => 'Monthly 2', 'type' => 'monthly']);
        $grandFinal = Exam::create(['branch_id' => $this->branch->id, 'name' => 'Grand Final', 'type' => 'grand_final']);

        [$w1, $m1] = $this->makeMarkConfigs($this->subject, $examOne);
        [$w2, $m2] = $this->makeMarkConfigs($this->subject, $examTwo);
        $student = $this->makeStudent('STU-1');

        ExamConfig::create([
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'merit_basis' => 'total_mark',
            'merit_sequential' => true,
        ])->exams()->sync([$examOne->id, $examTwo->id]);

        // Exam one: 80/100 (80%). Exam two: 40/100 (40%). Equal-weight average -> 60%.
        $this->postJson('/api/v1/examinations/marks', ['exam_id' => $examOne->id, 'entries' => [
            ['student_id' => $student->id, 'marks' => [
                ['mark_config_id' => $w1->id, 'obtained' => 56], ['mark_config_id' => $m1->id, 'obtained' => 24],
            ]],
        ]])->assertOk();
        $this->postJson('/api/v1/examinations/marks', ['exam_id' => $examTwo->id, 'entries' => [
            ['student_id' => $student->id, 'marks' => [
                ['mark_config_id' => $w2->id, 'obtained' => 28], ['mark_config_id' => $m2->id, 'obtained' => 12],
            ]],
        ]])->assertOk();

        $this->postJson('/api/v1/examinations/results/general-process', ['class_config_id' => $this->classConfig->id, 'exam_id' => $examOne->id])->assertOk();
        $this->postJson('/api/v1/examinations/results/general-process', ['class_config_id' => $this->classConfig->id, 'exam_id' => $examTwo->id])->assertOk();

        $response = $this->postJson('/api/v1/examinations/results/final-process', [
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $grandFinal->id,
        ]);
        $response->assertOk();

        $combined = StudentExamResult::where('student_id', $student->id)
            ->where('exam_id', $grandFinal->id)->firstOrFail();
        $this->assertEqualsWithDelta(60.0, (float) $combined->obtained_marks, 0.01);
        $this->assertEqualsWithDelta(100.0, (float) $combined->total_marks, 0.01);
        $this->assertTrue((bool) $combined->is_pass);
    }

    // ---- Merit process (GPA, 4th subject, failed-count, positions) ----------

    public function test_merit_process_computes_gpa_from_grade_points_without_a_fourth_subject(): void
    {
        $this->actingAsBranchUser();
        $english = Subject::create(['branch_id' => $this->branch->id, 'name' => 'English']);
        $student = $this->makeStudent('STU-1');

        // Math: A (4.00), English: A- (3.50) -> GPA = (4.00+3.50)/2 = 3.75
        $mathGrade = Grade::where('class_id', $this->class->id)->where('name', 'A')->firstOrFail();
        $englishGrade = Grade::where('class_id', $this->class->id)->where('name', 'A-')->firstOrFail();

        StudentExamResult::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id, 'exam_id' => $this->exam->id,
            'subject_id' => $this->subject->id, 'class_config_id' => $this->classConfig->id,
            'total_marks' => 100, 'obtained_marks' => 75, 'grade_id' => $mathGrade->id,
            'grade_point' => $mathGrade->grade_point, 'is_pass' => true, 'is_absent' => false,
        ]);
        StudentExamResult::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id, 'exam_id' => $this->exam->id,
            'subject_id' => $english->id, 'class_config_id' => $this->classConfig->id,
            'total_marks' => 100, 'obtained_marks' => 65, 'grade_id' => $englishGrade->id,
            'grade_point' => $englishGrade->grade_point, 'is_pass' => true, 'is_absent' => false,
        ]);

        $response = $this->postJson('/api/v1/examinations/results/merit-process', [
            'class_id' => $this->class->id,
            'exam_id' => $this->exam->id,
        ]);
        $response->assertOk();

        $summary = StudentExamSummary::where('student_id', $student->id)->firstOrFail();
        $this->assertEqualsWithDelta(3.75, (float) $summary->gpa, 0.001);
        $this->assertTrue((bool) $summary->is_pass);
        $this->assertSame(0, $summary->failed_subjects_count);
    }

    public function test_merit_process_applies_the_fourth_subject_bonus_and_excludes_it_from_the_denominator(): void
    {
        $this->actingAsBranchUser();
        $english = Subject::create(['branch_id' => $this->branch->id, 'name' => 'English']);
        $ict = Subject::create(['branch_id' => $this->branch->id, 'name' => 'ICT']);
        $student = $this->makeStudent('STU-1');
        $year = AcademicYear::firstOrCreate(['branch_id' => $this->branch->id, 'name' => '2025-2026'], ['is_current' => true]);

        StudentFourthSubject::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_year_id' => $year->id,
            'class_config_id' => $this->classConfig->id, 'subject_id' => $ict->id,
        ]);

        $aPlus = Grade::where('class_id', $this->class->id)->where('name', 'A+')->firstOrFail(); // 5.00
        $a = Grade::where('class_id', $this->class->id)->where('name', 'A')->firstOrFail(); // 4.00

        // Math A+ (5.00), English A+ (5.00), 4th subject (ICT) A (4.00) -> bonus = max(0, 4.00-2.00) = 2.00
        // GPA = (5.00 + 5.00 + 2.00) / 2 (compulsory count only) = 6.00
        foreach ([[$this->subject, $aPlus], [$english, $aPlus], [$ict, $a]] as [$subject, $grade]) {
            StudentExamResult::create([
                'branch_id' => $this->branch->id, 'student_id' => $student->id, 'exam_id' => $this->exam->id,
                'subject_id' => $subject->id, 'class_config_id' => $this->classConfig->id,
                'total_marks' => 100, 'obtained_marks' => 85, 'grade_id' => $grade->id,
                'grade_point' => $grade->grade_point, 'is_pass' => true, 'is_absent' => false,
            ]);
        }

        $this->postJson('/api/v1/examinations/results/merit-process', [
            'class_id' => $this->class->id,
            'exam_id' => $this->exam->id,
        ])->assertOk();

        $summary = StudentExamSummary::where('student_id', $student->id)->firstOrFail();
        $this->assertEqualsWithDelta(6.00, (float) $summary->gpa, 0.001);
    }

    public function test_failed_subjects_count_double_counts_an_absent_subject(): void
    {
        $this->actingAsBranchUser();
        $english = Subject::create(['branch_id' => $this->branch->id, 'name' => 'English']);
        $student = $this->makeStudent('STU-1');
        $grade = Grade::where('class_id', $this->class->id)->where('name', 'F')->firstOrFail();

        // One genuinely-failed subject (is_pass=false, not absent)...
        StudentExamResult::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id, 'exam_id' => $this->exam->id,
            'subject_id' => $this->subject->id, 'class_config_id' => $this->classConfig->id,
            'total_marks' => 100, 'obtained_marks' => 10, 'grade_id' => $grade->id,
            'grade_point' => 0, 'is_pass' => false, 'is_absent' => false,
        ]);
        // ...and one absent subject (is_pass=false AND is_absent=true — counted in both clauses).
        StudentExamResult::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id, 'exam_id' => $this->exam->id,
            'subject_id' => $english->id, 'class_config_id' => $this->classConfig->id,
            'total_marks' => 100, 'obtained_marks' => 0, 'grade_id' => $grade->id,
            'grade_point' => 0, 'is_pass' => false, 'is_absent' => true,
        ]);

        $this->postJson('/api/v1/examinations/results/merit-process', [
            'class_id' => $this->class->id,
            'exam_id' => $this->exam->id,
        ])->assertOk();

        $summary = StudentExamSummary::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(3, $summary->failed_subjects_count);
        $this->assertFalse((bool) $summary->is_pass);
        $this->assertNull($summary->class_position);
        $this->assertNull($summary->section_position);
    }

    public function test_merit_process_assigns_dense_positions_with_ties_when_non_sequential(): void
    {
        $this->actingAsBranchUser();
        ExamConfig::create([
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'merit_basis' => 'total_mark',
            'merit_sequential' => false,
        ]);

        $grade = Grade::where('class_id', $this->class->id)->where('name', 'A')->firstOrFail();
        $studentA = $this->makeStudent('STU-A', '1');
        $studentB = $this->makeStudent('STU-B', '2'); // tied with A
        $studentC = $this->makeStudent('STU-C', '3'); // strictly behind
        $entries = [[$studentA, 90], [$studentB, 90], [$studentC, 70]];

        foreach ($entries as [$student, $obtained]) {
            StudentExamResult::create([
                'branch_id' => $this->branch->id, 'student_id' => $student->id, 'exam_id' => $this->exam->id,
                'subject_id' => $this->subject->id, 'class_config_id' => $this->classConfig->id,
                'total_marks' => 100, 'obtained_marks' => $obtained, 'grade_id' => $grade->id,
                'grade_point' => $grade->grade_point, 'is_pass' => true, 'is_absent' => false,
            ]);
        }

        $this->postJson('/api/v1/examinations/results/merit-process', [
            'class_id' => $this->class->id,
            'exam_id' => $this->exam->id,
        ])->assertOk();

        $positions = StudentExamSummary::whereIn('student_id', [$studentA->id, $studentB->id, $studentC->id])
            ->get()->keyBy('student_id');

        $this->assertSame(1, $positions[$studentA->id]->class_position);
        $this->assertSame(1, $positions[$studentB->id]->class_position);
        // Dense ranking: the next distinct score jumps straight to 3, not 2.
        $this->assertSame(3, $positions[$studentC->id]->class_position);
    }

    public function test_merit_process_gives_every_student_a_distinct_rank_when_sequential(): void
    {
        $this->actingAsBranchUser();
        ExamConfig::create([
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'merit_basis' => 'total_mark',
            'merit_sequential' => true,
        ]);

        $grade = Grade::where('class_id', $this->class->id)->where('name', 'A')->firstOrFail();
        $students = [$this->makeStudent('STU-A', '1'), $this->makeStudent('STU-B', '2')];

        foreach ($students as $student) {
            StudentExamResult::create([
                'branch_id' => $this->branch->id, 'student_id' => $student->id, 'exam_id' => $this->exam->id,
                'subject_id' => $this->subject->id, 'class_config_id' => $this->classConfig->id,
                'total_marks' => 100, 'obtained_marks' => 90, 'grade_id' => $grade->id, // tied
                'grade_point' => $grade->grade_point, 'is_pass' => true, 'is_absent' => false,
            ]);
        }

        $this->postJson('/api/v1/examinations/results/merit-process', [
            'class_id' => $this->class->id,
            'exam_id' => $this->exam->id,
        ])->assertOk();

        $positions = StudentExamSummary::whereIn('student_id', [$students[0]->id, $students[1]->id])
            ->pluck('class_position')->all();

        // Sequential: even exact ties get distinct, consecutive ranks — never the same rank twice.
        $this->assertEqualsCanonicalizing([1, 2], $positions);
    }

    // ---- Branch isolation -----------------------------------------------------

    public function test_general_process_rejects_a_class_config_from_another_branch(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'slug' => 'other-org']);
        $otherBranch = Branch::create(['organization_id' => $otherOrg->id, 'name' => 'Other Branch', 'code' => 'OTHER']);
        app(BranchContext::class)->set($otherBranch->id);
        $otherClass = SchoolClass::create(['branch_id' => $otherBranch->id, 'name' => 'Ten']);
        $otherShift = Shift::create(['branch_id' => $otherBranch->id, 'name' => 'Morning']);
        $otherSection = Section::create(['branch_id' => $otherBranch->id, 'name' => 'A']);
        $foreignClassConfig = ClassConfig::create([
            'branch_id' => $otherBranch->id, 'class_id' => $otherClass->id,
            'shift_id' => $otherShift->id, 'section_id' => $otherSection->id,
        ]);

        app(BranchContext::class)->set($this->branch->id);
        $this->actingAsBranchUser();

        $response = $this->postJson('/api/v1/examinations/results/general-process', [
            'class_config_id' => $foreignClassConfig->id,
            'exam_id' => $this->exam->id,
        ]);

        $response->assertStatus(422);
    }
}
