<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceDevice;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function index(): JsonResponse
    {
        $devices = AttendanceDevice::orderBy('name')->get()->map(fn (AttendanceDevice $d) => $this->transform($d));

        return ApiResponse::success($devices, 'Devices retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $device = AttendanceDevice::create($data + ['created_by' => auth()->id()]);

        return ApiResponse::success($this->transform($device), 'Created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $device = AttendanceDevice::findOrFail($id);
        $device->update($this->validated($request, $id) + ['updated_by' => auth()->id()]);

        return ApiResponse::success($this->transform($device), 'Updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        AttendanceDevice::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function validated(Request $request, ?int $ignoreId): array
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $unique = Rule::unique('attendance_devices', 'device_uid')
            ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at'))
            ->ignore($ignoreId);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'device_uid' => ['required', 'string', 'max:100', $unique],
            'location' => ['nullable', 'string', 'max:150'],
            'ip_address' => ['nullable', 'ip'],
            'protocol' => ['sometimes', Rule::in(['zkteco', 'generic'])],
            'status' => ['sometimes', 'boolean'],
        ]);
    }

    private function transform(AttendanceDevice $d): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'device_uid' => $d->device_uid,
            'location' => $d->location,
            'ip_address' => $d->ip_address,
            'protocol' => $d->protocol,
            'status' => $d->status,
        ];
    }
}
