<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Models\Examinations\ClassTeacherConfig;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The signing "class teacher" for a class_config — appears on marksheets/admit cards. */
class ClassTeacherConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = ClassTeacherConfig::with(['classConfig.schoolClass', 'classConfig.section', 'classConfig.shift', 'employee'])
            ->get()
            ->map(fn (ClassTeacherConfig $c) => $this->transform($c));

        return ApiResponse::success($rows, 'Class teacher configs retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $config = ClassTeacherConfig::updateOrCreate(
            ['class_config_id' => $data['class_config_id']],
            ['employee_id' => $data['employee_id']],
        );

        return ApiResponse::success($this->transform($config->load(['classConfig.schoolClass', 'classConfig.section', 'classConfig.shift', 'employee'])), 'Saved.', status: 201);
    }

    public function destroy(int $id): JsonResponse
    {
        ClassTeacherConfig::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function validated(Request $request): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        return $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('branch_id', $branchId)],
        ]);
    }

    private function transform(ClassTeacherConfig $c): array
    {
        return [
            'id' => $c->id,
            'class_config_id' => $c->class_config_id,
            'class_label' => $c->classConfig?->label(),
            'employee_id' => $c->employee_id,
            'employee_name' => $c->employee?->name,
        ];
    }
}
