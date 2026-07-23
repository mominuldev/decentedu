<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Models\Academic\Subject;
use App\Models\Examinations\ExamRoutine;
use App\Models\Examinations\MarkConfig;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Exam-day schedule: subject x date/time/room, distinct from the weekly class_routines.
 * Enforces that a class isn't double-booked and a room isn't double-booked at the same time.
 */
class ExamRoutineController extends Controller
{
    /** Subjects configured for this class_config x exam (x group) — for building the form. */
    public function options(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)],
            'group_id' => ['nullable', 'integer'],
        ]);

        $subjectIds = MarkConfig::where('class_config_id', $data['class_config_id'])
            ->where('exam_id', $data['exam_id'])
            ->where('group_id', $data['group_id'] ?? null)
            ->distinct()->pluck('subject_id');

        return ApiResponse::success([
            'subjects' => Subject::whereIn('id', $subjectIds)->orderBy('serial')->get(['id', 'name']),
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

        $rows = ExamRoutine::with('subject')
            ->where('class_config_id', $data['class_config_id'])
            ->where('exam_id', $data['exam_id'])
            ->where('group_id', $data['group_id'] ?? null)
            ->orderBy('exam_date')->orderBy('start_time')
            ->get()
            ->map(fn (ExamRoutine $r) => $this->transform($r));

        return ApiResponse::success($rows, 'Exam routine retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $this->assertNoConflicts($data, null);

        $routine = ExamRoutine::create($data + ['created_by' => auth()->id()]);

        return ApiResponse::success($this->transform($routine->load('subject')), 'Created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $routine = ExamRoutine::findOrFail($id);
        $data = $this->validated($request, $id);
        $this->assertNoConflicts($data, $id);

        $routine->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success($this->transform($routine->load('subject')), 'Updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        ExamRoutine::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function validated(Request $request, ?int $ignoreId): array
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $inBranch = fn (string $table) => Rule::exists($table, 'id')->where('branch_id', $branchId);

        return $request->validate([
            'academic_year_id' => ['required', 'integer', $inBranch('academic_years')],
            'class_config_id' => ['required', 'integer', $inBranch('class_configs')],
            'group_id' => ['nullable', 'integer', $inBranch('groups')],
            'exam_id' => ['required', 'integer', $inBranch('exams')],
            'subject_id' => ['required', 'integer', $inBranch('subjects')],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room_no' => ['nullable', 'string', 'max:100'],
            'exam_session' => ['nullable', 'string', 'max:100'],
        ]);
    }

    /** The class must not already have another subject at an overlapping time on the same day; same for the room. */
    private function assertNoConflicts(array $data, ?int $ignoreId): void
    {
        $overlaps = fn ($q) => $q->where('exam_date', $data['exam_date'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));

        $classBusy = ExamRoutine::where('class_config_id', $data['class_config_id'])
            ->where('subject_id', '!=', $data['subject_id'])
            ->tap($overlaps)
            ->exists();
        abort_if($classBusy, 422, 'This class already has another subject scheduled at an overlapping time.');

        if (! empty($data['room_no'])) {
            $roomBusy = ExamRoutine::where('room_no', $data['room_no'])
                ->where('class_config_id', '!=', $data['class_config_id'])
                ->tap($overlaps)
                ->exists();
            abort_if($roomBusy, 422, 'This room is already booked for another class at an overlapping time.');
        }
    }

    private function transform(ExamRoutine $r): array
    {
        return [
            'id' => $r->id,
            'academic_year_id' => $r->academic_year_id,
            'class_config_id' => $r->class_config_id,
            'group_id' => $r->group_id,
            'exam_id' => $r->exam_id,
            'subject_id' => $r->subject_id,
            'subject_name' => $r->subject?->name,
            'exam_date' => $r->exam_date?->toDateString(),
            'start_time' => $r->start_time,
            'end_time' => $r->end_time,
            'room_no' => $r->room_no,
            'exam_session' => $r->exam_session,
        ];
    }
}
