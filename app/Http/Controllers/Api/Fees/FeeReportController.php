<?php

namespace App\Http\Controllers\Api\Fees;

use App\Http\Controllers\Controller;
use App\Models\Fees\FeeCollection;
use App\Models\Fees\StudentFee;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeeReportController extends Controller
{
    public function show(Request $request, string $type): JsonResponse
    {
        return match ($type) {
            'daily-collection' => $this->dailyCollection($request),
            'dues-summary' => $this->duesSummary($request),
            default => abort(404, 'Unknown report.'),
        };
    }

    /** Collections in a date range, grouped by fee head. */
    private function dailyCollection(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $collections = FeeCollection::with('items.studentFee.feeSubHead.feeHead')
            ->whereDate('collected_at', '>=', $data['from'])
            ->whereDate('collected_at', '<=', $data['to'])
            ->get();

        $byHead = [];
        foreach ($collections as $collection) {
            foreach ($collection->items as $item) {
                $head = $item->studentFee->feeSubHead->feeHead?->name ?? 'Unknown';
                $byHead[$head] = ($byHead[$head] ?? 0) + (float) $item->amount;
            }
        }

        return ApiResponse::success([
            'from' => $data['from'],
            'to' => $data['to'],
            'total_collected' => round((float) $collections->sum('total_amount'), 2),
            'receipts_count' => $collections->count(),
            'by_head' => collect($byHead)->map(fn ($amount, $head) => ['fee_head_name' => $head, 'amount' => round($amount, 2)])->values(),
        ], 'Daily collection report retrieved.');
    }

    /** Outstanding dues, grouped by class_config, for one academic year. */
    private function duesSummary(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
        ]);

        $dues = StudentFee::with('classConfig.schoolClass', 'classConfig.section')
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('status', '!=', 'paid')
            ->get()
            ->groupBy('class_config_id')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'class_config_id' => $first->class_config_id,
                    'class_label' => $first->classConfig?->label(),
                    'students_with_dues' => $rows->pluck('student_id')->unique()->count(),
                    'total_due' => round($rows->sum(fn (StudentFee $sf) => $sf->dueAmount()), 2),
                ];
            })
            ->values();

        return ApiResponse::success($dues, 'Dues summary retrieved.');
    }
}
