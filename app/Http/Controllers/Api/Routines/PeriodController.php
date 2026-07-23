<?php

namespace App\Http\Controllers\Api\Routines;

use App\Http\Controllers\Controller;
use App\Models\Routines\Period;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Timetable slots (e.g. "Period 1", 09:00-09:45), scoped to a shift. */
class PeriodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Period::with('shift')->orderBy('serial')->orderBy('id');

        if ($shiftId = $request->integer('shift_id')) {
            $query->where('shift_id', $shiftId);
        }

        $items = $query->get()->map(fn (Period $p) => $this->transform($p));

        return ApiResponse::success($items, 'Periods retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $period = Period::create($data + ['created_by' => auth()->id()]);

        return ApiResponse::success($this->transform($period->load('shift')), 'Created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $period = Period::findOrFail($id);
        $period->update($this->validated($request, $id) + ['updated_by' => auth()->id()]);

        return ApiResponse::success($this->transform($period->load('shift')), 'Updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Period::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function validated(Request $request, ?int $ignoreId): array
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $inBranch = fn (string $table) => Rule::exists($table, 'id')->where('branch_id', $branchId);

        $unique = Rule::unique('periods', 'name')
            ->where(fn ($q) => $q->where('branch_id', $branchId)->where('shift_id', $request->input('shift_id'))->whereNull('deleted_at'))
            ->ignore($ignoreId);

        return $request->validate([
            'shift_id' => ['required', 'integer', $inBranch('shifts')],
            'name' => ['required', 'string', 'max:100', $unique],
            'name_bn' => ['nullable', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'serial' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ]);
    }

    private function transform(Period $p): array
    {
        return [
            'id' => $p->id,
            'shift_id' => $p->shift_id,
            'shift_name' => $p->shift?->name,
            'name' => $p->name,
            'name_bn' => $p->name_bn,
            'start_time' => substr((string) $p->start_time, 0, 5),
            'end_time' => substr((string) $p->end_time, 0, 5),
            'serial' => $p->serial,
            'status' => $p->status,
        ];
    }
}
