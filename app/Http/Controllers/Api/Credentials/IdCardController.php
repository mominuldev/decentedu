<?php

namespace App\Http\Controllers\Api\Credentials;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Credentials\IdCardTemplate;
use App\Models\Hr\Employee;
use App\Models\Students\Enrollment;
use App\Models\Students\Student;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Stateless bulk ID-card render data — no PDF, no persistence of "issued" cards (matches the
 * "simple config, no visual builder" decision). The frontend lays these out on a fixed card
 * component and prints via the browser.
 */
class IdCardController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'template_id' => ['required', 'integer', Rule::exists('id_card_templates', 'id')->where('branch_id', $branchId)],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'class_config_id' => ['nullable', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
        ]);

        $template = IdCardTemplate::findOrFail($data['template_id']);

        $holders = $template->holder_type === 'student'
            ? $this->students($data['ids'] ?? [], $data['class_config_id'] ?? null)
            : $this->employees($data['ids'] ?? []);

        $cards = $holders->map(fn (array $holder) => array_intersect_key($holder, array_flip($template->fields)))->values();

        return ApiResponse::success([
            'template' => $template,
            'branch' => Branch::find($branchId),
            'cards' => $cards,
        ], 'ID cards generated.');
    }

    private const CLASS_CONFIG_CHAIN = ['currentEnrollment.classConfig.schoolClass', 'currentEnrollment.classConfig.section', 'currentEnrollment.classConfig.shift'];

    private function students(array $ids, ?int $classConfigId): \Illuminate\Support\Collection
    {
        $students = $classConfigId
            ? Enrollment::with(array_merge(['student'], array_map(fn ($p) => 'student.'.$p, self::CLASS_CONFIG_CHAIN)))
                ->where('class_config_id', $classConfigId)->current()->get()->pluck('student')->filter()
            : Student::whereIn('id', $ids)->with(self::CLASS_CONFIG_CHAIN)->get();

        return $students->map(fn (Student $s) => [
            'photo' => $s->photo_path,
            'name' => $s->name,
            'roll' => $s->currentEnrollment?->roll,
            'class' => $s->currentEnrollment?->classConfig?->label(),
            'blood_group' => $s->blood_group,
            'address' => $s->present_address,
            'guardian' => $s->fathers_name,
            'mobile' => $s->mobile ?: $s->father_mobile,
            'validity' => null,
            'signature' => null,
        ]);
    }

    private function employees(array $ids): \Illuminate\Support\Collection
    {
        return Employee::whereIn('id', $ids)->get()->map(fn (Employee $e) => [
            'photo' => $e->photo_path,
            'name' => $e->name,
            'designation' => $e->designation?->name,
            'blood_group' => $e->blood_group,
            'address' => $e->present_address,
            'mobile' => $e->mobile,
            'validity' => null,
            'signature' => null,
        ]);
    }
}
