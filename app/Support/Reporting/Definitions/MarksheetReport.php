<?php

namespace App\Support\Reporting\Definitions;

use App\Models\Examinations\StudentExamResult;
use App\Models\Examinations\StudentExamSummary;
use App\Support\BranchContext;
use App\Support\Reporting\ReportDefinition;
use Illuminate\Validation\Rule;

/** Per-student, per-subject breakdown for one class_config x exam — the printable marksheet. */
class MarksheetReport extends ReportDefinition
{
    public function key(): string
    {
        return 'marksheet';
    }

    public function title(): string
    {
        return 'Marksheet';
    }

    public function rules(): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        return [
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)],
        ];
    }

    public function data(array $params): array
    {
        $summaries = StudentExamSummary::with('student')
            ->where('class_config_id', $params['class_config_id'])
            ->where('exam_id', $params['exam_id'])
            ->get()
            ->keyBy('student_id');

        $subjectResults = StudentExamResult::with(['subject', 'grade'])
            ->where('class_config_id', $params['class_config_id'])
            ->where('exam_id', $params['exam_id'])
            ->get()
            ->groupBy('student_id');

        $rows = $subjectResults->map(function ($subjectRows, $studentId) use ($summaries) {
            $summary = $summaries->get($studentId);

            return [
                'student_id' => $studentId,
                'name' => $subjectRows->first()->student?->name,
                'subjects' => $subjectRows->map(fn (StudentExamResult $r) => [
                    'subject_name' => $r->subject?->name,
                    'total_marks' => $r->total_marks,
                    'obtained_marks' => $r->obtained_marks,
                    'grade' => $r->grade?->name,
                    'grade_point' => $r->grade_point,
                    'is_pass' => $r->is_pass,
                    'is_absent' => $r->is_absent,
                ])->values(),
                'total_marks' => $summary?->total_marks,
                'total_obtained' => $summary?->total_obtained,
                'gpa' => $summary?->gpa,
                'is_pass' => $summary?->is_pass,
                'class_position' => $summary?->class_position,
                'section_position' => $summary?->section_position,
            ];
        })->values();

        return ['rows' => $rows, 'branch' => $this->branch()];
    }

    public function pdfView(): ?string
    {
        return 'reports.examinations.marksheet';
    }

    public function excelHeadings(): ?array
    {
        return ['Student', 'Total Marks', 'Total Obtained', 'GPA', 'Result', 'Class Position', 'Section Position'];
    }

    public function excelRows(array $data): array
    {
        return $data['rows']->map(fn (array $r) => [
            $r['name'], $r['total_marks'], $r['total_obtained'], $r['gpa'], $r['is_pass'] ? 'Pass' : 'Fail', $r['class_position'], $r['section_position'],
        ])->all();
    }
}
