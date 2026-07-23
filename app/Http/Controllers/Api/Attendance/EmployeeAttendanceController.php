<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance\EmployeeAttendance;
use App\Models\Hr\Employee;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeAttendanceController extends Controller
{
    private const STATUSES = ['present', 'absent', 'late', 'leave', 'half_day'];

    /** All active employees for a date, with attendance (default "present" if unmarked). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date']]);

        $employees = Employee::active()->orderBy('name')->get();

        $attendances = EmployeeAttendance::where('date', $data['date'])->get()->keyBy('employee_id');

        $rows = $employees->map(function (Employee $e) use ($attendances) {
            $att = $attendances->get($e->id);

            return [
                'employee_id' => $e->id,
                'name' => $e->name,
                'name_bn' => $e->name_bn,
                'designation_id' => $e->designation_id,
                'attendance_id' => $att?->id,
                'status' => $att?->status ?? 'present',
                'remarks' => $att?->remarks,
            ];
        })->values();

        return ApiResponse::success($rows, 'Roster retrieved.');
    }

    /** Bulk take/update attendance for all staff on one date. */
    public function take(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'date' => ['required', 'date'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('branch_id', $branchId)],
            'entries.*.status' => ['required', Rule::in(self::STATUSES)],
            'entries.*.remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = auth()->id();

        foreach ($data['entries'] as $entry) {
            EmployeeAttendance::updateOrCreate(
                ['employee_id' => $entry['employee_id'], 'date' => $data['date']],
                [
                    'status' => $entry['status'],
                    'remarks' => $entry['remarks'] ?? null,
                    'marked_by' => $userId,
                    'source' => 'manual',
                ],
            );
        }

        return ApiResponse::success(null, 'Attendance saved.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $attendance = EmployeeAttendance::findOrFail($id);
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $attendance->update($data + ['marked_by' => auth()->id()]);

        return ApiResponse::success(['id' => $attendance->id, 'status' => $attendance->status, 'remarks' => $attendance->remarks], 'Updated.');
    }

    /** Per-employee attendance totals over a date range. */
    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $rows = EmployeeAttendance::with('employee')
            ->whereBetween('date', [$data['from'], $data['to']])
            ->get()
            ->groupBy('employee_id')
            ->map(function ($records) {
                $employee = $records->first()->employee;

                return [
                    'employee_id' => $employee?->id,
                    'name' => $employee?->name,
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
