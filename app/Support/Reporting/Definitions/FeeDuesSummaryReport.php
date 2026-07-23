<?php

namespace App\Support\Reporting\Definitions;

use App\Models\Fees\StudentFee;
use App\Support\BranchContext;
use App\Support\Reporting\ReportDefinition;
use Illuminate\Validation\Rule;

/**
 * Outstanding dues, grouped by class_config, for one academic year — a whole-branch scan,
 * so this is the one report definition flagged queued() to exercise the artifact job path.
 */
class FeeDuesSummaryReport extends ReportDefinition
{
    public function key(): string
    {
        return 'fee-dues-summary';
    }

    public function title(): string
    {
        return 'Dues Summary Report';
    }

    public function rules(): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
        ];
    }

    public function data(array $params): array
    {
        $rows = StudentFee::with('classConfig.schoolClass', 'classConfig.section')
            ->where('academic_year_id', $params['academic_year_id'])
            ->where('status', '!=', 'paid')
            ->get()
            ->groupBy('class_config_id')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'class_config_id' => $first->class_config_id,
                    'class_label' => $first->classConfig?->label(),
                    'students_with_dues' => $group->pluck('student_id')->unique()->count(),
                    'total_due' => round($group->sum(fn (StudentFee $sf) => $sf->dueAmount()), 2),
                ];
            })
            ->values();

        return ['rows' => $rows, 'branch' => $this->branch()];
    }

    public function pdfView(): ?string
    {
        return 'reports.fees.dues-summary';
    }

    public function excelHeadings(): ?array
    {
        return ['Class', 'Students With Dues', 'Total Due'];
    }

    public function excelRows(array $data): array
    {
        return $data['rows']->map(fn (array $r) => [$r['class_label'], $r['students_with_dues'], $r['total_due']])->all();
    }

    public function queued(): bool
    {
        return true;
    }
}
