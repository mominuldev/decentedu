<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Jobs\SendAbsenteeAttendanceNotice;
use App\Models\Attendance\StudentAttendance;
use App\Models\Students\Enrollment;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentAttendanceController extends Controller
{
    private const STATUSES = ['present', 'absent', 'late', 'leave', 'half_day'];

    /** The class roster for a date, with each student's attendance (default "present" if unmarked). */
    public function index(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'date' => ['required', 'date'],
        ]);

        $enrollments = Enrollment::with('student')
            ->where('class_config_id', $data['class_config_id'])
            ->current()
            ->get()
            ->sortBy('roll');

        $attendances = StudentAttendance::where('class_config_id', $data['class_config_id'])
            ->where('date', $data['date'])
            ->get()
            ->keyBy('student_id');

        $rows = $enrollments->map(function (Enrollment $e) use ($attendances) {
            $att = $attendances->get($e->student_id);

            return [
                'student_id' => $e->student_id,
                'enrollment_id' => $e->id,
                'roll' => $e->roll,
                'name' => $e->student?->name,
                'name_bn' => $e->student?->name_bn,
                'photo_path' => $e->student?->photo_path,
                'attendance_id' => $att?->id,
                'status' => $att?->status ?? 'present',
                'remarks' => $att?->remarks,
            ];
        })->values();

        return ApiResponse::success($rows, 'Roster retrieved.');
    }

    /** Bulk take/update attendance for a whole class on one date. */
    public function take(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'date' => ['required', 'date'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('branch_id', $branchId)],
            'entries.*.status' => ['required', Rule::in(self::STATUSES)],
            'entries.*.remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = auth()->id();
        $newlyAbsent = [];

        foreach ($data['entries'] as $entry) {
            $enrollment = Enrollment::where('student_id', $entry['student_id'])
                ->where('class_config_id', $data['class_config_id'])
                ->current()
                ->first();

            $existing = StudentAttendance::where('student_id', $entry['student_id'])->where('date', $data['date'])->first();

            $attendance = StudentAttendance::updateOrCreate(
                ['student_id' => $entry['student_id'], 'date' => $data['date']],
                [
                    'enrollment_id' => $enrollment?->id,
                    'class_config_id' => $data['class_config_id'],
                    'status' => $entry['status'],
                    'remarks' => $entry['remarks'] ?? null,
                    'marked_by' => $userId,
                    'source' => 'manual',
                ],
            );

            if ($entry['status'] === 'absent' && $existing?->status !== 'absent') {
                $newlyAbsent[] = $attendance->id;
            }
        }

        foreach ($newlyAbsent as $attendanceId) {
            SendAbsenteeAttendanceNotice::dispatch($attendanceId);
        }

        return ApiResponse::success(null, 'Attendance saved.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $attendance = StudentAttendance::findOrFail($id);
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $wasAbsent = $attendance->status === 'absent';
        $attendance->update($data + ['marked_by' => auth()->id()]);

        if (! $wasAbsent && $attendance->status === 'absent') {
            SendAbsenteeAttendanceNotice::dispatch($attendance->id);
        }

        return ApiResponse::success(['id' => $attendance->id, 'status' => $attendance->status, 'remarks' => $attendance->remarks], 'Updated.');
    }

    /** Per-student attendance totals for a class over a date range. */
    public function report(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $rows = StudentAttendance::with('student')
            ->where('class_config_id', $data['class_config_id'])
            ->whereBetween('date', [$data['from'], $data['to']])
            ->get()
            ->groupBy('student_id')
            ->map(function ($records) {
                $student = $records->first()->student;

                return [
                    'student_id' => $student?->id,
                    'name' => $student?->name,
                    'present' => $records->where('status', 'present')->count(),
                    'absent' => $records->where('status', 'absent')->count(),
                    'late' => $records->where('status', 'late')->count(),
                    'leave' => $records->where('status', 'leave')->count(),
                    'half_day' => $records->where('status', 'half_day')->count(),
                    'total_days' => $records->count(),
                ];
            })
            ->values();

        return ApiResponse::success($rows, 'Report generated.');
    }
}
