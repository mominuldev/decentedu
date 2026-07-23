<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAttendancePunches;
use App\Models\Attendance\AttendanceDevice;
use App\Models\Attendance\AttendancePunch;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ingests raw punches pushed from a biometric device (or a manual "simulate sync"
 * in the absence of real hardware) and queues their resolution into daily
 * student/employee attendance via ProcessAttendancePunches.
 */
class PunchController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'device_uid' => ['required', 'string', Rule::exists('attendance_devices', 'device_uid')->where('branch_id', $branchId)],
            'punches' => ['required', 'array', 'min:1'],
            'punches.*.external_user_id' => ['required', 'string'],
            'punches.*.punched_at' => ['required', 'date'],
            'punches.*.direction' => ['nullable', Rule::in(['in', 'out'])],
        ]);

        $device = AttendanceDevice::where('device_uid', $data['device_uid'])->firstOrFail();

        $rows = collect($data['punches'])->map(fn (array $p) => [
            'branch_id' => $branchId,
            'attendance_device_id' => $device->id,
            'external_user_id' => $p['external_user_id'],
            'punched_at' => $p['punched_at'],
            'direction' => $p['direction'] ?? null,
            'raw_payload' => json_encode($p),
            'processed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AttendancePunch::insert($rows->all());

        return ApiResponse::success(['ingested' => $rows->count()], 'Punches ingested.', status: 201);
    }

    /** Queue resolution of this branch's unprocessed punches into daily attendance. */
    public function process(): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        ProcessAttendancePunches::dispatch($branchId);

        return ApiResponse::success(null, 'Punch processing queued.');
    }
}
