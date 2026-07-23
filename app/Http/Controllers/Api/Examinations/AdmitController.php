<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Models\Examinations\AdmitInstruction;
use App\Models\Examinations\ExamRoutine;
use App\Models\Examinations\Signature;
use App\Models\Students\Enrollment;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admit card, seat plan and attendance sheet data for one class_config x exam.
 * [inferred: legacy's seat allocation algorithm was flagged as a gap even in the
 * source analysis (docs/02 §"Roll/seat uniqueness... 🔧") — this is a simple
 * sequential-by-roll placeholder, not a reproduction of a confirmed legacy algorithm.]
 */
class AdmitController extends Controller
{
    private function roster(int $classConfigId, ?int $groupId): \Illuminate\Support\Collection
    {
        return Enrollment::with('student')
            ->where('class_config_id', $classConfigId)
            ->when($groupId, fn ($q) => $q->where('group_id', $groupId))
            ->current()
            ->get()
            ->sortBy('roll')
            ->values();
    }

    public function admitCard(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)],
            'group_id' => ['nullable', 'integer'],
        ]);

        $students = $this->roster($data['class_config_id'], $data['group_id'] ?? null)->map(fn (Enrollment $e) => [
            'student_id' => $e->student_id,
            'roll' => $e->roll,
            'name' => $e->student?->name,
            'photo_path' => $e->student?->photo_path,
        ]);

        $routine = ExamRoutine::with('subject')
            ->where('class_config_id', $data['class_config_id'])
            ->where('exam_id', $data['exam_id'])
            ->orderBy('exam_date')->orderBy('start_time')
            ->get()
            ->map(fn (ExamRoutine $r) => [
                'subject_name' => $r->subject?->name,
                'exam_date' => $r->exam_date?->toDateString(),
                'start_time' => $r->start_time,
                'end_time' => $r->end_time,
                'room_no' => $r->room_no,
            ]);

        return ApiResponse::success([
            'students' => $students,
            'routine' => $routine,
            'instructions' => AdmitInstruction::where('branch_id', $branchId)->first(),
            'signatures' => Signature::where('status', true)->orderBy('position')->get(['position', 'person_name', 'designation']),
        ], 'Admit card data retrieved.');
    }

    /** Sequential seat allocation by roll, filling one room to capacity before moving to the next. */
    public function seatPlan(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'group_id' => ['nullable', 'integer'],
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.name' => ['required', 'string'],
            'rooms.*.capacity' => ['required', 'integer', 'min:1'],
        ]);

        $students = $this->roster($data['class_config_id'], $data['group_id'] ?? null);
        $rows = collect();
        $roomIndex = 0;
        $seatInRoom = 0;

        foreach ($students as $e) {
            while ($roomIndex < count($data['rooms']) && $seatInRoom >= $data['rooms'][$roomIndex]['capacity']) {
                $roomIndex++;
                $seatInRoom = 0;
            }
            if ($roomIndex >= count($data['rooms'])) {
                break; // out of capacity
            }
            $seatInRoom++;
            $rows->push([
                'student_id' => $e->student_id,
                'roll' => $e->roll,
                'name' => $e->student?->name,
                'room' => $data['rooms'][$roomIndex]['name'],
                'seat_no' => $seatInRoom,
            ]);
        }

        return ApiResponse::success($rows->values(), 'Seat plan generated.');
    }

    public function attendanceSheet(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'group_id' => ['nullable', 'integer'],
        ]);

        $rows = $this->roster($data['class_config_id'], $data['group_id'] ?? null)->map(fn (Enrollment $e) => [
            'student_id' => $e->student_id,
            'roll' => $e->roll,
            'name' => $e->student?->name,
        ]);

        return ApiResponse::success($rows, 'Attendance sheet retrieved.');
    }
}
