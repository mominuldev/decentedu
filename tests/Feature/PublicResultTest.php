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
use App\Models\Examinations\Grade;
use App\Models\Examinations\StudentExamResult;
use App\Models\Examinations\StudentExamSummary;
use App\Models\Organization;
use App\Models\Students\Enrollment;
use App\Models\Students\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicResultTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicYear $academicYear;

    private ClassConfig $classConfig;

    private Exam $exam;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $this->branch = Branch::create(['organization_id' => $org->id, 'name' => 'Main Branch', 'code' => 'MAIN']);

        // Create academic year
        $this->academicYear = new AcademicYear([
            'name' => '2024-2025',
            'is_current' => true,
            'status' => true,
        ]);
        $this->academicYear->branch_id = $this->branch->id;
        $this->academicYear->save();

        // Create required setup records for class config
        $class = new SchoolClass(['name' => 'Class 10', 'status' => true]);
        $class->branch_id = $this->branch->id;
        $class->save();

        $section = new Section(['name' => 'Section A', 'status' => true]);
        $section->branch_id = $this->branch->id;
        $section->save();

        $shift = new Shift(['name' => 'Morning', 'status' => true]);
        $shift->branch_id = $this->branch->id;
        $shift->save();

        // Create class config
        $this->classConfig = new ClassConfig([
            'academic_year_id' => $this->academicYear->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'shift_id' => $shift->id,
            'status' => true,
        ]);
        $this->classConfig->branch_id = $this->branch->id;
        $this->classConfig->save();

        // Create exam
        $this->exam = new Exam(['name' => 'Annual Exam 2024', 'status' => true]);
        $this->exam->branch_id = $this->branch->id;
        $this->exam->save();

        // Create student
        $student = new Student([
            'student_uid' => 'STD2024001',
            'name' => 'Test Student',
            'sex' => 'male',
            'fathers_name' => 'Father Name',
            'mothers_name' => 'Mother Name',
        ]);
        $student->branch_id = $this->branch->id;
        $student->save();

        // Create enrollment
        $this->enrollment = new Enrollment([
            'academic_year_id' => $this->academicYear->id,
            'class_config_id' => $this->classConfig->id,
            'student_id' => $student->id,
            'roll' => '101',
            'status' => true,
        ]);
        $this->enrollment->branch_id = $this->branch->id;
        $this->enrollment->save();
    }

    public function test_can_get_result_search_options(): void
    {
        $response = $this->getJson('/api/v1/site/results/options');

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'data' => [
                'academic_years',
                'sections',
                'class_configs',
                'exams',
            ],
        ]);
    }

    public function test_can_search_student_result_by_section_only(): void
    {
        $summary = new StudentExamSummary([
            'student_id' => $this->enrollment->student_id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'total_marks' => 500,
            'total_obtained' => 400,
            'gpa' => 4.00,
            'is_pass' => true,
        ]);
        $summary->branch_id = $this->branch->id;
        $summary->save();

        $response = $this->postJson('/api/v1/site/results/search', [
            'academic_year_id' => $this->academicYear->id,
            'section_id' => $this->classConfig->section_id,
            'exam_id' => $this->exam->id,
            'roll_no' => '101',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertEquals('101', $data['student']['roll_no']);
        $this->assertEquals(4.00, $data['summary']['gpa']);
    }

    public function test_can_search_student_result_with_valid_roll_number(): void
    {
        // Create required records for exam result
        $grade = new Grade(['name' => 'A+', 'grade_point' => 5.0, 'mark_from' => 80, 'mark_to' => 100]);
        $grade->branch_id = $this->branch->id;
        $grade->class_id = $this->classConfig->class_id;
        $grade->save();

        $subject = new Subject(['name' => 'Mathematics', 'code' => 'MATH', 'status' => true]);
        $subject->branch_id = $this->branch->id;
        $subject->save();

        $summary = new StudentExamSummary([
            'student_id' => $this->enrollment->student_id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'total_marks' => 500,
            'total_obtained' => 425,
            'gpa' => 4.50,
            'is_pass' => true,
            'class_position' => 5,
            'section_position' => 2,
        ]);
        $summary->branch_id = $this->branch->id;
        $summary->save();

        $result = new StudentExamResult([
            'student_id' => $this->enrollment->student_id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'subject_id' => $subject->id,
            'total_marks' => 100,
            'obtained_marks' => 85,
            'grade_id' => $grade->id,
            'grade_point' => 5.0,
            'is_pass' => true,
            'is_absent' => false,
        ]);
        $result->branch_id = $this->branch->id;
        $result->save();

        $response = $this->postJson('/api/v1/site/results/search', [
            'academic_year_id' => $this->academicYear->id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'roll_no' => '101',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'data' => [
                'student',
                'exam',
                'academic_year',
                'summary',
                'subjects',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals('101', $data['student']['roll_no']);
        $this->assertEquals(4.50, $data['summary']['gpa']);
        $this->assertTrue($data['summary']['is_pass']);
        $this->assertCount(1, $data['subjects']);
    }

    public function test_can_search_student_result_with_student_uid(): void
    {
        // Create exam result data
        $summary = new StudentExamSummary([
            'student_id' => $this->enrollment->student_id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'total_marks' => 500,
            'total_obtained' => 380,
            'gpa' => 3.80,
            'is_pass' => true,
        ]);
        $summary->branch_id = $this->branch->id;
        $summary->save();

        $response = $this->postJson('/api/v1/site/results/search', [
            'academic_year_id' => $this->academicYear->id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'roll_no' => 'STD2024001', // Using student_uid instead of roll_no
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertEquals('STD2024001', $data['student']['student_uid']);
        $this->assertEquals('101', $data['student']['roll_no']);
    }

    public function test_search_fails_with_invalid_roll_number(): void
    {
        $response = $this->postJson('/api/v1/site/results/search', [
            'academic_year_id' => $this->academicYear->id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'roll_no' => '99999', // Non-existent roll number
        ]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
        $response->assertJsonFragment([
            'message' => 'No student found matching the provided Roll / Student ID in this class and year.',
        ]);
    }

    public function test_search_fails_when_results_not_published(): void
    {
        $response = $this->postJson('/api/v1/site/results/search', [
            'academic_year_id' => $this->academicYear->id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'roll_no' => '101',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
        $response->assertJsonFragment([
            'message' => 'Results for the selected exam have not been published or processed yet.',
        ]);
    }

    public function test_search_validation_requires_all_fields(): void
    {
        $response = $this->postJson('/api/v1/site/results/search', [
            'academic_year_id' => $this->academicYear->id,
            // Missing exam_id, roll_no (class_config_id / section_id are optional alternatives)
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['exam_id', 'roll_no']);
    }

    public function test_can_download_marksheet_pdf(): void
    {
        // Create required records for PDF test
        $grade = new Grade(['name' => 'A', 'grade_point' => 4.0, 'mark_from' => 70, 'mark_to' => 79]);
        $grade->branch_id = $this->branch->id;
        $grade->class_id = $this->classConfig->class_id;
        $grade->save();

        $subject = new Subject(['name' => 'Physics', 'code' => 'PHY', 'status' => true]);
        $subject->branch_id = $this->branch->id;
        $subject->save();

        $result = new StudentExamResult([
            'student_id' => $this->enrollment->student_id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'subject_id' => $subject->id,
            'total_marks' => 100,
            'obtained_marks' => 85,
            'grade_id' => $grade->id,
            'grade_point' => 5.0,
            'is_pass' => true,
            'is_absent' => false,
        ]);
        $result->branch_id = $this->branch->id;
        $result->save();

        $response = $this->postJson('/api/v1/site/results/marksheet/pdf', [
            'academic_year_id' => $this->academicYear->id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'roll_no' => '101',
        ]);

        $response->assertOk();
        // PDF should be returned as a stream
        $this->assertNotEmpty($response->getContent());
    }

    public function test_can_download_marksheet_pdf_by_section_only(): void
    {
        $grade = new Grade(['name' => 'A', 'grade_point' => 4.0, 'mark_from' => 70, 'mark_to' => 79]);
        $grade->branch_id = $this->branch->id;
        $grade->class_id = $this->classConfig->class_id;
        $grade->save();

        $subject = new Subject(['name' => 'Physics', 'code' => 'PHY', 'status' => true]);
        $subject->branch_id = $this->branch->id;
        $subject->save();

        $result = new StudentExamResult([
            'student_id' => $this->enrollment->student_id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'subject_id' => $subject->id,
            'total_marks' => 100,
            'obtained_marks' => 85,
            'grade_id' => $grade->id,
            'grade_point' => 5.0,
            'is_pass' => true,
            'is_absent' => false,
        ]);
        $result->branch_id = $this->branch->id;
        $result->save();

        $response = $this->postJson('/api/v1/site/results/marksheet/pdf', [
            'academic_year_id' => $this->academicYear->id,
            'section_id' => $this->classConfig->section_id,
            'exam_id' => $this->exam->id,
            'roll_no' => '101',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->getContent());
    }

    public function test_pdf_download_fails_for_invalid_student(): void
    {
        $response = $this->postJson('/api/v1/site/results/marksheet/pdf', [
            'academic_year_id' => $this->academicYear->id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'roll_no' => '99999',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    public function test_pdf_download_fails_when_marksheet_not_generated(): void
    {
        // Student exists but no results are generated
        $response = $this->postJson('/api/v1/site/results/marksheet/pdf', [
            'academic_year_id' => $this->academicYear->id,
            'class_config_id' => $this->classConfig->id,
            'exam_id' => $this->exam->id,
            'roll_no' => '101',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
        $response->assertJsonFragment([
            'message' => 'Marksheet for the selected student has not been generated or processed.',
        ]);
    }
}
