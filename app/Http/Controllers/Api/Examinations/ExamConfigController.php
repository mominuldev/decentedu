<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Models\Examinations\ExamConfig;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Per class: which exams count toward the result, and how merit/position is computed. */
class ExamConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = ExamConfig::with(['schoolClass', 'exams'])->orderBy('class_id')->get()
            ->map(fn (ExamConfig $c) => $this->transform($c));

        return ApiResponse::success($rows, 'Exam configs retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $config = ExamConfig::updateOrCreate(
            ['class_id' => $data['class_id']],
            [
                'merit_basis' => $data['merit_basis'],
                'merit_sequential' => $data['merit_sequential'],
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ],
        );
        $config->exams()->sync($data['exam_ids']);

        return ApiResponse::success($this->transform($config->load(['schoolClass', 'exams'])), 'Saved.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $config = ExamConfig::findOrFail($id);
        $data = $this->validated($request, $id);

        $config->update([
            'merit_basis' => $data['merit_basis'],
            'merit_sequential' => $data['merit_sequential'],
            'updated_by' => auth()->id(),
        ]);
        $config->exams()->sync($data['exam_ids']);

        return ApiResponse::success($this->transform($config->load(['schoolClass', 'exams'])), 'Updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        ExamConfig::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        return $request->validate([
            'class_id' => [
                'required', 'integer',
                Rule::exists('classes', 'id')->where('branch_id', $branchId),
                Rule::unique('exam_configs', 'class_id')->where('branch_id', $branchId)->ignore($ignoreId),
            ],
            'merit_basis' => ['required', Rule::in(['total_mark', 'grade_point'])],
            'merit_sequential' => ['required', 'boolean'],
            'exam_ids' => ['required', 'array', 'min:1'],
            'exam_ids.*' => ['integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)],
        ]);
    }

    private function transform(ExamConfig $c): array
    {
        return [
            'id' => $c->id,
            'class_id' => $c->class_id,
            'class_name' => $c->schoolClass?->name,
            'merit_basis' => $c->merit_basis,
            'merit_sequential' => $c->merit_sequential,
            'exam_ids' => $c->exams->pluck('id'),
            'exam_names' => $c->exams->pluck('name'),
        ];
    }
}
