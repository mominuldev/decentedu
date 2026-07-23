<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\Subject;
use App\Models\Examinations\MarkConfig;
use App\Models\Examinations\ShortCode;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Per class_config x group x exam: which subjects use which mark components
 * (short codes), and each component's total/pass mark.
 */
class MarkConfigController extends Controller
{
    /** Subjects + short codes available for building the form. */
    public function options(): JsonResponse
    {
        return ApiResponse::success([
            'subjects' => Subject::where('status', true)->orderBy('serial')->get(['id', 'name']),
            'short_codes' => ShortCode::where('status', true)->orderBy('serial')->get(['id', 'name']),
        ], 'Options retrieved.');
    }

    public function index(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)],
            'group_id' => ['nullable', 'integer'],
        ]);

        $rows = MarkConfig::with(['subject', 'shortCode'])
            ->where('class_config_id', $data['class_config_id'])
            ->where('exam_id', $data['exam_id'])
            ->where('group_id', $data['group_id'] ?? null)
            ->orderBy('subject_id')->orderBy('serial')
            ->get()
            ->map(fn (MarkConfig $m) => $this->transform($m));

        return ApiResponse::success($rows, 'Mark configs retrieved.');
    }

    /** Bulk save the components for one class_config x exam (x group). */
    public function save(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->where('branch_id', $branchId)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('branch_id', $branchId)],
            'items.*.short_code_id' => ['required', 'integer', Rule::exists('short_codes', 'id')->where('branch_id', $branchId)],
            'items.*.total_marks' => ['required', 'numeric', 'min:1'],
            'items.*.pass_mark' => ['required', 'numeric', 'min:0', 'lte:items.*.total_marks'],
            'items.*.acceptance' => ['nullable', 'numeric', 'min:0'],
            'items.*.sc_merge' => ['sometimes', 'boolean'],
        ]);

        $userId = auth()->id();
        $saved = collect($data['items'])->map(function (array $item) use ($data, $userId) {
            return MarkConfig::updateOrCreate(
                [
                    'class_config_id' => $data['class_config_id'],
                    'group_id' => $data['group_id'] ?? null,
                    'exam_id' => $data['exam_id'],
                    'subject_id' => $item['subject_id'],
                    'short_code_id' => $item['short_code_id'],
                ],
                [
                    'total_marks' => $item['total_marks'],
                    'pass_mark' => $item['pass_mark'],
                    'acceptance' => $item['acceptance'] ?? null,
                    'sc_merge' => $item['sc_merge'] ?? false,
                    'updated_by' => $userId,
                    'created_by' => $userId,
                ],
            );
        });

        return ApiResponse::success(
            $saved->each->load(['subject', 'shortCode'])->map(fn (MarkConfig $m) => $this->transform($m)),
            'Mark configuration saved.',
        );
    }

    public function destroy(int $id): JsonResponse
    {
        MarkConfig::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function transform(MarkConfig $m): array
    {
        return [
            'id' => $m->id,
            'class_config_id' => $m->class_config_id,
            'group_id' => $m->group_id,
            'exam_id' => $m->exam_id,
            'subject_id' => $m->subject_id,
            'subject_name' => $m->subject?->name,
            'short_code_id' => $m->short_code_id,
            'short_code_name' => $m->shortCode?->name,
            'total_marks' => $m->total_marks,
            'pass_mark' => $m->pass_mark,
            'acceptance' => $m->acceptance,
            'sc_merge' => $m->sc_merge,
            'status' => $m->status,
        ];
    }
}
