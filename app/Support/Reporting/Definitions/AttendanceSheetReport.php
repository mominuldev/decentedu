<?php

namespace App\Support\Reporting\Definitions;

use App\Models\Students\Enrollment;
use App\Support\BranchContext;
use App\Support\Reporting\Definitions\Concerns\BuildsExamRoster;
use App\Support\Reporting\ReportDefinition;
use Illuminate\Validation\Rule;

class AttendanceSheetReport extends ReportDefinition
{
    use BuildsExamRoster;

    public function key(): string
    {
        return 'attendance-sheet';
    }

    public function title(): string
    {
        return 'Exam Attendance Sheet';
    }

    public function rules(): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        return [
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'group_id' => ['nullable', 'integer'],
        ];
    }

    public function data(array $params): array
    {
        $rows = $this->roster($params['class_config_id'], $params['group_id'] ?? null)->map(fn (Enrollment $e) => [
            'student_id' => $e->student_id,
            'roll' => $e->roll,
            'name' => $e->student?->name,
        ]);

        return ['rows' => $rows, 'branch' => $this->branch()];
    }

    public function pdfView(): ?string
    {
        return 'reports.examinations.attendance-sheet';
    }

    public function excelHeadings(): ?array
    {
        return ['Roll', 'Name', 'Signature'];
    }

    public function excelRows(array $data): array
    {
        return $data['rows']->map(fn (array $r) => [$r['roll'], $r['name'], ''])->all();
    }
}
