<?php

namespace App\Http\Controllers\Api\Fees;

use App\Http\Controllers\Controller;
use App\Models\Fees\FeeSubHead;
use App\Models\Fees\FeeTimeConfig;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Due date + flat fine per fee_sub_head, for one academic_year. */
class FeeTimeConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
        ]);

        $subHeads = FeeSubHead::with('feeHead')->where('status', true)->orderBy('serial')->get();
        $existing = FeeTimeConfig::where('academic_year_id', $data['academic_year_id'])->get()->keyBy('fee_sub_head_id');

        $rows = $subHeads->map(fn (FeeSubHead $sh) => [
            'fee_sub_head_id' => $sh->id,
            'fee_sub_head_name' => $sh->name,
            'fee_head_name' => $sh->feeHead?->name,
            'due_date' => $existing->get($sh->id)?->due_date?->toDateString(),
            'fine_amount' => $existing->get($sh->id)?->fine_amount,
        ]);

        return ApiResponse::success($rows, 'Fee time configuration retrieved.');
    }

    public function save(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
            'items' => ['required', 'array'],
            'items.*.fee_sub_head_id' => ['required', 'integer', Rule::exists('fee_sub_heads', 'id')->where('branch_id', $branchId)],
            'items.*.due_date' => ['required', 'date'],
            'items.*.fine_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $saved = collect();
        DB::transaction(function () use ($data, &$saved) {
            foreach ($data['items'] as $item) {
                $saved->push(FeeTimeConfig::updateOrCreate(
                    ['fee_sub_head_id' => $item['fee_sub_head_id'], 'academic_year_id' => $data['academic_year_id']],
                    ['due_date' => $item['due_date'], 'fine_amount' => $item['fine_amount'], 'updated_by' => auth()->id(), 'created_by' => auth()->id()],
                ));
            }
        });

        return ApiResponse::success($saved, 'Fee time configuration saved.');
    }
}
