<?php

namespace App\Support\Reporting\Definitions;

use App\Models\Academic\ClassConfig;
use App\Models\Examinations\StudentExamSummary;
use App\Support\BranchContext;
use App\Support\Reporting\ReportDefinition;
use Illuminate\Validation\Rule;

class MeritListReport extends ReportDefinition
{
    public function key(): string
    {
        return 'merit-list';
    }

    public function title(): string
    {
        return 'Merit List';
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
            ->where('is_pass', true);

        if (! empty($params['class_config_id'])) {
            $query->where('class_config_id', $params['class_config_id'])->orderBy('section_position');
        } else {
            $classConfigIds = ClassConfig::where('class_id', $params['class_id'])->pluck('id');
            $query->whereIn('class_config_id', $classConfigIds)->orderBy('class_position');
        }

        $rows = $query->get()->map(fn (StudentExamSummary $s) => [
            'student_id' => $s->student_id,
            'name' => $s->student?->name,
            'section' => $s->classConfig?->section?->name,
            'total_obtained' => $s->total_obtained,
            'gpa' => $s->gpa,
            'position' => ! empty($params['class_config_id']) ? $s->section_position : $s->class_position,
        ])->values();

        return ['rows' => $rows, 'branch' => $this->branch()];
    }

    public function pdfView(): ?string
    {
        return 'reports.examinations.merit-list';
    }

    public function excelHeadings(): ?array
    {
        return ['Position', 'Name', 'Section', 'Total Obtained', 'GPA'];
    }

    public function excelRows(array $data): array
    {
        return $data['rows']->map(fn (array $r) => [$r['position'], $r['name'], $r['section'], $r['total_obtained'], $r['gpa']])->all();
    }
}
