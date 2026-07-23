<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\Group;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ClassConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ClassConfig::with(['schoolClass', 'shift', 'section'])->orderBy('serial')->orderBy('id');

        if ($classId = $request->integer('class_id')) {
            $query->where('class_id', $classId);
        }

        $items = $query->get()->map(fn (ClassConfig $c) => $this->transform($c));

        return ApiResponse::success($items, 'Class configs retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $config = ClassConfig::create($data);

        return ApiResponse::success($this->transform($config->load(['schoolClass', 'shift', 'section'])), 'Created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $config = ClassConfig::findOrFail($id);
        $config->update($this->validated($request, $id));

        return ApiResponse::success($this->transform($config->load(['schoolClass', 'shift', 'section'])), 'Updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        ClassConfig::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    /**
     * Select options (classes, shifts, sections, groups) — hit by nearly every form in the
     * app (students, routines, attendance, exams, fees). Writes go through the generic
     * Academic\SetupController across 4 different resource slugs, so rather than wire
     * invalidation into every one of them, this relies on a short TTL (doc 08: "reference
     * lists ... short TTL") — worst case a renamed/added class takes up to a minute to show.
     */
    public function options(): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = Cache::remember("class-config-options:{$branchId}", 60, fn () => [
            'classes' => SchoolClass::where('status', true)->orderBy('serial')->get(['id', 'name']),
            'shifts' => Shift::where('status', true)->orderBy('serial')->get(['id', 'name']),
            'sections' => Section::where('status', true)->orderBy('serial')->get(['id', 'name']),
            'groups' => Group::where('status', true)->orderBy('serial')->get(['id', 'name']),
        ]);

        return ApiResponse::success($data, 'Options retrieved.');
    }

    private function validated(Request $request, ?int $ignoreId): array
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $inBranch = fn (string $table) => Rule::exists($table, 'id')->where('branch_id', $branchId);

        $data = $request->validate([
            'class_id' => ['required', 'integer', $inBranch('classes')],
            'shift_id' => ['required', 'integer', $inBranch('shifts')],
            'section_id' => ['required', 'integer', $inBranch('sections')],
            'serial' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ]);

        // Enforce the unique (branch, class, shift, section) combination.
        $exists = ClassConfig::where('class_id', $data['class_id'])
            ->where('shift_id', $data['shift_id'])
            ->where('section_id', $data['section_id'])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        abort_if($exists, 422, 'This class · section · shift combination already exists.');

        return $data;
    }

    private function transform(ClassConfig $c): array
    {
        return [
            'id' => $c->id,
            'class_id' => $c->class_id,
            'shift_id' => $c->shift_id,
            'section_id' => $c->section_id,
            'class_name' => $c->schoolClass?->name,
            'shift_name' => $c->shift?->name,
            'section_name' => $c->section?->name,
            'label' => $c->label(),
            'serial' => $c->serial,
            'status' => $c->status,
        ];
    }
}
