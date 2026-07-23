<?php

namespace App\Support\Reporting\Definitions;

use App\Models\Academic\ClassConfig;
use App\Models\Examinations\StudentExamResult;
use App\Models\Examinations\StudentExamSummary;
use App\Support\BranchContext;
use App\Support\Reporting\ReportDefinition;
use Illuminate\Validation\Rule;

class FailListReport extends ReportDefinition
{
    public function key(): string
    {
        return 'fail-list';
    }

    public function title(): string
    {
        return 'Fail List';
    }

    public function rules(): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        return [
            'exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)],
            'class_id' => ['nullable', 'integer', Rule::exists('classes', 'id')->where('branch_id', $branchId)],
            'class_config_id' => ['nullable', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
        ];
    }

    public function data(array $params): array
    {
        abort_if(empty($params['class_id']) && empty($params['class_config_id']), 422, 'Provide class_id (class-wise) or class_config_id (section-wise).');

        $query = StudentExamSummary::with(['student', 'classConfig.section'])
            ->where('exam_id', $params['exam_id'])
            ->where('is_pass', false);

        if (! empty($params['class_config_id'])) {
            $query->where('class_config_id', $params['class_config_id']);
        } else {
            $classConfigIds = ClassConfig::where('class_id', $params['class_id'])->pluck('id');
            $query->whereIn('class_config_id', $classConfigIds);
        }

        $summaries = $query->get();
        $failedSubjects = StudentExamResult::where('exam_id', $params['exam_id'])
            ->whereIn('student_id', $summaries->pluck('student_id'))
            ->where('is_pass', false)
            ->with('subject')
            ->get()
            ->groupBy('student_id');

        $rows = $summaries->map(fn (StudentExamSummary $s) => [
            'student_id' => $s->student_id,
            'name' => $s->student?->name,
            'section' => $s->classConfig?->section?->name,
            'total_obtained' => $s->total_obtained,
            'failed_subjects' => $failedSubjects->get($s->student_id, collect())->pluck('subject.name'),
        ])->values();

        return ['rows' => $rows, 'branch' => $this->branch()];
    }

    public function pdfView(): ?string
    {
        return 'reports.examinations.fail-list';
    }

    public function excelHeadings(): ?array
    {
        return ['Name', 'Section', 'Total Obtained', 'Failed Subjects'];
    }

    public function excelRows(array $data): array
    {
        return $data['rows']->map(fn (array $r) => [$r['name'], $r['section'], $r['total_obtained'], $r['failed_subjects']->join(', ')])->all();
    }
}
