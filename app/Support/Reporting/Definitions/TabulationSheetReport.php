<?php

namespace App\Support\Reporting\Definitions;

use App\Models\Examinations\StudentExamResult;
use App\Support\BranchContext;
use App\Support\Reporting\ReportDefinition;
use Illuminate\Validation\Rule;

/** Subject x student matrix for one class_config x exam. */
class TabulationSheetReport extends ReportDefinition
{
    public function key(): string
    {
        return 'tabulation-sheet';
    }

    public function title(): string
    {
        return 'Tabulation Sheet';
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
        $results = StudentExamResult::with(['student', 'subject'])
            ->where('class_config_id', $params['class_config_id'])
            ->where('exam_id', $params['exam_id'])
            ->get();

        $subjects = $results->pluck('subject')->unique('id')->values()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]);

        $rows = $results->groupBy('student_id')->map(function ($studentRows, $studentId) {
            $bySubject = $studentRows->keyBy('subject_id');

            return [
                'student_id' => $studentId,
                'name' => $studentRows->first()->student?->name,
                'marks' => $bySubject->map(fn (StudentExamResult $r) => $r->is_absent ? 'Ab' : $r->obtained_marks),
            ];
        })->values();

        return ['subjects' => $subjects, 'rows' => $rows, 'branch' => $this->branch()];
    }

    public function pdfView(): ?string
    {
        return 'reports.examinations.tabulation-sheet';
    }

    public function excelHeadings(): ?array
    {
        return null; // matrix shape (dynamic subject columns) — PDF/print only
    }
}
