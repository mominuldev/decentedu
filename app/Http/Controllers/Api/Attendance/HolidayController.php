<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance\Holiday;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Holiday::query()->orderBy('date');

        if ($year = $request->integer('year')) {
            $query->whereYear('date', $year);
        }

        return ApiResponse::success($query->get()->map(fn (Holiday $h) => $this->transform($h)), 'Holidays retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $holiday = Holiday::create($data + ['created_by' => auth()->id()]);

        return ApiResponse::success($this->transform($holiday), 'Created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $holiday = Holiday::findOrFail($id);
        $holiday->update($this->validated($request, $id) + ['updated_by' => auth()->id()]);

        return ApiResponse::success($this->transform($holiday), 'Updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Holiday::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function validated(Request $request, ?int $ignoreId): array
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $unique = Rule::unique('holidays', 'date')
            ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at'))
            ->ignore($ignoreId);

        return $request->validate([
            'date' => ['required', 'date', $unique],
            'title' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'type' => ['sometimes', Rule::in(['public', 'weekend', 'other'])],
            'status' => ['sometimes', 'boolean'],
        ]);
    }

    private function transform(Holiday $h): array
    {
        return [
            'id' => $h->id,
            'date' => $h->date->toDateString(),
            'title' => $h->title,
            'name_bn' => $h->name_bn,
            'type' => $h->type,
            'status' => $h->status,
        ];
    }
}
