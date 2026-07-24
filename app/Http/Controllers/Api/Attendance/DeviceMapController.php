<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceDeviceMap;
use App\Models\Hr\Employee;
use App\Models\Students\Student;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Maps a biometric device's internal user id to one of our students/employees. */
class DeviceMapController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceDeviceMap::with(['device', 'mappable'])->orderBy('id', 'desc');

        if ($deviceId = $request->integer('attendance_device_id')) {
            $query->where('attendance_device_id', $deviceId);
        }

        return ApiResponse::success($query->get()->map(fn (AttendanceDeviceMap $m) => $this->transform($m)), 'Device maps retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'attendance_device_id' => ['required', 'integer', Rule::exists('attendance_devices', 'id')->where('branch_id', $branchId)],
            'external_user_id' => [
                'required', 'string', 'max:100',
                Rule::unique('attendance_device_maps', 'external_user_id')
                    ->where(fn ($q) => $q->where('branch_id', $branchId)->where('attendance_device_id', $request->input('attendance_device_id'))->whereNull('deleted_at')),
            ],
            'mappable_type' => ['required', Rule::in(['student', 'employee'])],
            'mappable_id' => ['required', 'integer'],
        ]);

        $modelClass = $data['mappable_type'] === 'student' ? Student::class : Employee::class;
        abort_unless($modelClass::whereKey($data['mappable_id'])->exists(), 422, 'The selected person was not found in this branch.');

        $map = AttendanceDeviceMap::create([
            'attendance_device_id' => $data['attendance_device_id'],
            'external_user_id' => $data['external_user_id'],
            'mappable_type' => $data['mappable_type'],
            'mappable_id' => $data['mappable_id'],
            'status' => true,
            'created_by' => auth()->id(),
        ]);

        return ApiResponse::success($this->transform($map->load(['device', 'mappable'])), 'Created.', status: 201);
    }

    public function destroy(int $id): JsonResponse
    {
        AttendanceDeviceMap::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function transform(AttendanceDeviceMap $m): array
    {
        return [
            'id' => $m->id,
            'attendance_device_id' => $m->attendance_device_id,
            'device_name' => $m->device?->name,
            'external_user_id' => $m->external_user_id,
            'mappable_type' => $m->mappable_type,
            'mappable_id' => $m->mappable_id,
            'mappable_name' => $m->mappable?->name,
            'status' => $m->status,
        ];
    }
}
