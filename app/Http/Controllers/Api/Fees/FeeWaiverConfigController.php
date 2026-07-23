<?php

namespace App\Http\Controllers\Api\Fees;

use App\Http\Controllers\Controller;
use App\Models\Fees\FeeWaiverConfig;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Per-student waiver assignment (a scholarship/staff-ward discount applied to one or all sub-heads). */
class FeeWaiverConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('branch_id', $branchId)],
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
        ]);

        $rows = FeeWaiverConfig::with(['feeWaiver', 'feeSubHead'])
            ->where('student_id', $data['student_id'])
            ->when(! empty($data['academic_year_id']), fn ($q) => $q->where('academic_year_id', $data['academic_year_id']))
            ->get()
            ->map(fn (FeeWaiverConfig $w) => [
                'id' => $w->id,
                'fee_waiver_id' => $w->fee_waiver_id,
                'fee_waiver_name' => $w->feeWaiver?->name,
                'fee_sub_head_id' => $w->fee_sub_head_id,
                'fee_sub_head_name' => $w->feeSubHead?->name ?? 'All fees',
                'academic_year_id' => $w->academic_year_id,
            ]);

        return ApiResponse::success($rows, 'Waiver assignments retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('branch_id', $branchId)],
            'fee_waiver_id' => ['required', 'integer', Rule::exists('fee_waivers', 'id')->where('branch_id', $branchId)],
            'fee_sub_head_id' => ['nullable', 'integer', Rule::exists('fee_sub_heads', 'id')->where('branch_id', $branchId)],
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
        ]);

        $model = FeeWaiverConfig::create($data + ['created_by' => auth()->id()]);

        return ApiResponse::success($model, 'Waiver assigned.', status: 201);
    }

    public function destroy(int $id): JsonResponse
    {
        FeeWaiverConfig::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Waiver assignment removed.');
    }
}
