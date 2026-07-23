<?php

namespace App\Http\Controllers\Api\Routines;

use App\Http\Controllers\Controller;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\Subject;
use App\Models\Hr\Employee;
use App\Models\Routines\ClassRoutine;
use App\Models\Routines\Period;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Class routine: a weekly grid of (day x period) -> subject + teacher (+ room) per
 * class_config. Enforces three conflict rules on every write: the slot itself, the
 * teacher, and the room must each be free at that day + period.
 */
class ClassRoutineController extends Controller
{
    /** Periods (for the class_config's shift), subjects and teachers — for building the form. */
    public function options(int $classConfigId): JsonResponse
    {
        $classConfig = ClassConfig::findOrFail($classConfigId);

        return ApiResponse::success([
            'periods' => Period::where('shift_id', $classConfig->shift_id)->where('status', true)
                ->orderBy('serial')->get(['id', 'name', 'start_time', 'end_time']),
            'subjects' => Subject::where('status', true)->orderBy('serial')->get(['id', 'name']),
            'employees' => Employee::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ], 'Options retrieved.');
    }

    /** Weekly grid for one class_config. */
    public function forClassConfig(int $classConfigId): JsonResponse
    {
        ClassConfig::findOrFail($classConfigId);

        $rows = ClassRoutine::with(['period', 'subject', 'employee'])
            ->where('class_config_id', $classConfigId)
            ->get()
            ->map(fn (ClassRoutine $r) => $this->transform($r));

        return ApiResponse::success($rows, 'Class routine retrieved.');
    }

    /** Weekly grid for one teacher, across all class_configs. */
    public function forTeacher(int $employeeId): JsonResponse
    {
        Employee::findOrFail($employeeId);

        $rows = ClassRoutine::with(['period', 'subject', 'classConfig.schoolClass', 'classConfig.section', 'classConfig.shift'])
            ->where('employee_id', $employeeId)
            ->get()
            ->map(fn (ClassRoutine $r) => $this->transform($r));

        return ApiResponse::success($rows, 'Teacher routine retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $this->assertNoConflicts($data, null);

        $routine = ClassRoutine::create($data + ['created_by' => auth()->id()]);

        return ApiResponse::success($this->transform($routine->load(['period', 'subject', 'employee'])), 'Created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $routine = ClassRoutine::findOrFail($id);
        $data = $this->validated($request, $id);
        $this->assertNoConflicts($data, $id);

        $routine->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success($this->transform($routine->load(['period', 'subject', 'employee'])), 'Updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        ClassRoutine::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function validated(Request $request, ?int $ignoreId): array
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $inBranch = fn (string $table) => Rule::exists($table, 'id')->where('branch_id', $branchId);

        return $request->validate([
            'class_config_id' => ['required', 'integer', $inBranch('class_configs')],
            'period_id' => ['required', 'integer', $inBranch('periods')],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'subject_id' => ['required', 'integer', $inBranch('subjects')],
            'employee_id' => ['nullable', 'integer', $inBranch('employees')],
            'room' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'boolean'],
        ]);
    }

    /** The slot, the teacher and the room must each be free at that day + period. */
    private function assertNoConflicts(array $data, ?int $ignoreId): void
    {
        $base = fn () => ClassRoutine::where('day_of_week', $data['day_of_week'])
            ->where('period_id', $data['period_id'])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));

        $slotTaken = (clone $base())->where('class_config_id', $data['class_config_id'])->exists();
        abort_if($slotTaken, 422, 'This class already has a subject scheduled for that day and period.');

        if (! empty($data['employee_id'])) {
            $teacherBusy = (clone $base())->where('employee_id', $data['employee_id'])
                ->where('class_config_id', '!=', $data['class_config_id'])
                ->exists();
            abort_if($teacherBusy, 422, 'This teacher is already assigned to another class at that day and period.');
        }

        if (! empty($data['room'])) {
            $roomBusy = (clone $base())->where('room', $data['room'])
                ->where('class_config_id', '!=', $data['class_config_id'])
                ->exists();
            abort_if($roomBusy, 422, 'This room is already booked for another class at that day and period.');
        }
    }

    private function transform(ClassRoutine $r): array
    {
        return [
            'id' => $r->id,
            'class_config_id' => $r->class_config_id,
            'period_id' => $r->period_id,
            'period_name' => $r->period?->name,
            'day_of_week' => $r->day_of_week,
            'subject_id' => $r->subject_id,
            'subject_name' => $r->subject?->name,
            'employee_id' => $r->employee_id,
            'employee_name' => $r->employee?->name,
            'class_label' => $r->relationLoaded('classConfig') ? $r->classConfig?->label() : null,
            'room' => $r->room,
            'status' => $r->status,
        ];
    }
}
