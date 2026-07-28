<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms\Public;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Examinations\Exam;
use App\Models\Examinations\StudentExamResult;
use App\Models\Examinations\StudentExamSummary;
use App\Models\Students\Enrollment;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use App\Support\Reporting\Definitions\MarksheetReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicResultController extends Controller
{
    /** Get options (Years, Class Configs, Exams) for public search. */
    public function options(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->id() ?? config('cms.public_branch_id', 1);

        $academicYears = AcademicYear::where('branch_id', $branchId)
            ->where('status', true)
            ->orderByDesc('is_current')
            ->orderByDesc('id')
            ->get(['id', 'name', 'is_current']);

        $classConfigs = ClassConfig::with(['schoolClass:id,name', 'section:id,name', 'shift:id,name'])
            ->where('branch_id', $branchId)
            ->where('status', true)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'label' => $c->label(),
                'class_name' => $c->schoolClass?->name,
                'section_name' => $c->section?->name,
                'shift_name' => $c->shift?->name,
            ]);

        $exams = Exam::where('branch_id', $branchId)
            ->where('status', true)
            ->orderBy('id')
            ->get(['id', 'name']);

        return ApiResponse::success([
            'academic_years' => $academicYears,
            'class_configs' => $classConfigs,
            'exams' => $exams,
        ], 'Result search options retrieved.');
    }

    /** Search individual student result by roll, year, class_config & exam. */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'class_config_id' => ['required', 'integer'],
            'exam_id' => ['required', 'integer'],
            'roll_no' => ['required', 'string'],
        ]);

        $enrollment = Enrollment::with(['student', 'classConfig.schoolClass', 'classConfig.section', 'classConfig.shift'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('class_config_id', $data['class_config_id'])
            ->where(function ($q) use ($data) {
                $q->where('roll', $data['roll_no'])
                  ->orWhereHas('student', fn ($sq) => $sq->where('student_uid', $data['roll_no']));
            })
            ->first();

        if (! $enrollment || ! $enrollment->student) {
            return ApiResponse::error('No student found matching the provided Roll / Student ID in this class and year.', 'NOT_FOUND', 404);
        }

        $summary = StudentExamSummary::where('student_id', $enrollment->student_id)
            ->where('class_config_id', $data['class_config_id'])
            ->where('exam_id', $data['exam_id'])
            ->first();

        $subjectResults = StudentExamResult::with(['subject', 'grade'])
            ->where('student_id', $enrollment->student_id)
            ->where('class_config_id', $data['class_config_id'])
            ->where('exam_id', $data['exam_id'])
            ->get();

        if (! $summary && $subjectResults->isEmpty()) {
            return ApiResponse::error('Results for the selected exam have not been published or processed yet.', 'NOT_FOUND', 404);
        }

        $exam = Exam::find($data['exam_id']);
        $year = AcademicYear::find($data['academic_year_id']);

        return ApiResponse::success([
            'student' => [
                'id' => $enrollment->student->id,
                'name' => $enrollment->student->name,
                'name_bn' => $enrollment->student->name_bn,
                'student_uid' => $enrollment->student->student_uid,
                'roll_no' => $enrollment->roll,
                'class_name' => $enrollment->classConfig?->schoolClass?->name,
                'section_name' => $enrollment->classConfig?->section?->name,
                'shift_name' => $enrollment->classConfig?->shift?->name,
            ],
            'exam' => [
                'id' => $exam?->id,
                'name' => $exam?->name,
            ],
            'academic_year' => [
                'id' => $year?->id,
                'name' => $year?->name,
            ],
            'summary' => $summary ? [
                'total_marks' => $summary->total_marks,
                'total_obtained' => $summary->total_obtained,
                'gpa' => $summary->gpa,
                'is_pass' => $summary->is_pass,
                'class_position' => $summary->class_position,
                'section_position' => $summary->section_position,
            ] : null,
            'subjects' => $subjectResults->map(fn ($r) => [
                'subject_name' => $r->subject?->name,
                'subject_code' => $r->subject?->code,
                'total_marks' => $r->total_marks,
                'obtained_marks' => $r->obtained_marks,
                'grade' => $r->grade?->name,
                'grade_point' => $r->grade_point,
                'is_pass' => $r->is_pass,
                'is_absent' => $r->is_absent,
            ])->values(),
        ], 'Student result retrieved successfully.');
    }

    /** Download individual student marksheet PDF. */
    public function downloadPdf(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'class_config_id' => ['required', 'integer'],
            'exam_id' => ['required', 'integer'],
            'roll_no' => ['required', 'string'],
        ]);

        $enrollment = Enrollment::where('academic_year_id', $data['academic_year_id'])
            ->where('class_config_id', $data['class_config_id'])
            ->where(function ($q) use ($data) {
                $q->where('roll', $data['roll_no'])
                  ->orWhereHas('student', fn ($sq) => $sq->where('student_uid', $data['roll_no']));
            })
            ->first();

        if (! $enrollment) {
            return ApiResponse::error('No student found matching the provided details.', 'NOT_FOUND', 404);
        }

        $definition = app(MarksheetReport::class);
        $reportData = $definition->data([
            'class_config_id' => $data['class_config_id'],
            'exam_id' => $data['exam_id'],
        ]);

        $studentRows = collect($reportData['rows'])->filter(fn ($r) => $r['student_id'] === $enrollment->student_id)->values();

        if ($studentRows->isEmpty()) {
            return ApiResponse::error('Marksheet for the selected student has not been generated or processed.', 'NOT_FOUND', 404);
        }

        $singleStudentData = [
            'rows' => $studentRows,
            'branch' => $reportData['branch'] ?? null,
        ];

        $pdf = Pdf::loadView($definition->pdfView(), ['title' => 'Marksheet - ' . ($studentRows[0]['name'] ?? ''), 'data' => $singleStudentData]);

        return $pdf->stream('marksheet-' . $data['roll_no'] . '.pdf');
    }
}
