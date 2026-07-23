<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceTimeConfig;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Expected in/out time + late grace, per student class_config or for all employees. */
class TimeConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceTimeConfig::with('classConfig.schoolClass', 'classConfig.section', 'classConfig.shift')
            ->orderBy('applicable_to');

        if ($applicableTo = $request->query('applicable_to')) {
            $query->where('applicable_to', $applicableTo);
        }

        return ApiResponse::success($query->get()->map(fn (AttendanceTimeConfig $c) => $this->transform($c)), 'Time configs retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $this->assertNotDuplicate($data, null);

        $config = AttendanceTimeConfig::create($data + ['created_by' => auth()->id()]);

        return ApiResponse::success($this->transform($config->load('classConfig')), 'Created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $config = AttendanceTimeConfig::findOrFail($id);
        $data = $this->validated($request, $id);
        $this->assertNotDuplicate($data, $id);

        $config->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success($this->transform($config->load('classConfig')), 'Updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        AttendanceTimeConfig::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function validated(Request $request, ?int $ignoreId): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        return $request->validate([
            'applicable_to' => ['required', Rule::in(['student', 'employee'])],
            'class_config_id' => ['nullable', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'in_time' => ['required', 'date_format:H:i'],
            'out_time' => ['required', 'date_format:H:i', 'after:in_time'],
            'late_after' => ['required', 'date_format:H:i', 'after_or_equal:in_time'],
            'status' => ['sometimes', 'boolean'],
        ]);
    }

    /** One config per (applicable_to, class_config_id) — class_config_id null = the default for that scope. */
    private function assertNotDuplicate(array $data, ?int $ignoreId): void
    {
        $exists = AttendanceTimeConfig::where('applicable_to', $data['applicable_to'])
            ->where('class_config_id', $data['class_config_id'] ?? null)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        abort_if($exists, 422, 'A time config for this scope already exists.');
    }

    private function transform(AttendanceTimeConfig $c): array
    {
        return [
            'id' => $c->id,
            'applicable_to' => $c->applicable_to,
            'class_config_id' => $c->class_config_id,
            'class_label' => $c->classConfig?->label(),
            'in_time' => substr((string) $c->in_time, 0, 5),
            'out_time' => substr((string) $c->out_time, 0, 5),
            'late_after' => substr((string) $c->late_after, 0, 5),
            'status' => $c->status,
        ];
    }
}
