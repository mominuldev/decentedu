<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Models\Examinations\Grade;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Grade scale (A+, A, B…) with a grade point and a mark-percentage range, per class. */
class GradeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('branch_id', $branchId)],
        ]);

        $rows = Grade::where('class_id', $data['class_id'])->orderByDesc('mark_from')->get();

        return ApiResponse::success($rows, 'Grades retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $grade = Grade::create($data + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]);

        return ApiResponse::success($grade, 'Created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $grade = Grade::findOrFail($id);
        $data = $this->validated($request, $id);
        $grade->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success($grade, 'Updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Grade::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function validated(Request $request, ?int $ignoreId): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('branch_id', $branchId)],
            'name' => ['required', 'string', 'max:20'],
            'grade_point' => ['required', 'numeric', 'min:0', 'max:5'],
            'mark_from' => ['required', 'numeric', 'min:0', 'max:100'],
            'mark_to' => ['required', 'numeric', 'min:0', 'max:100', 'gte:mark_from'],
            'serial' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ]);

        $overlap = Grade::where('class_id', $data['class_id'])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('mark_from', '<=', $data['mark_to'])
            ->where('mark_to', '>=', $data['mark_from'])
            ->exists();
        abort_if($overlap, 422, 'This mark range overlaps an existing grade for this class.');

        return $data;
    }
}
