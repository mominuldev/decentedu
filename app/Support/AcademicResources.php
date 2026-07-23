<?php

namespace App\Support;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Category;
use App\Models\Academic\Group;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Academic\Subject;
use Illuminate\Validation\Rule;

/**
 * Registry of the uniform branch-scoped "setup" resources. One entry per
 * reference table drives the generic SetupController (list/create/update/delete)
 * and keeps ~7 near-identical CRUD endpoints DRY.
 */
class AcademicResources
{
    public static function all(): array
    {
        return [
            'academic-years' => [
                'model' => AcademicYear::class,
                'table' => 'academic_years',
                'fields' => ['name', 'name_bn', 'start_date', 'end_date', 'is_current', 'serial', 'status'],
                'exclusive' => 'is_current', // only one current year per branch
                'extraRules' => [
                    'start_date' => ['nullable', 'date'],
                    'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                    'is_current' => ['sometimes', 'boolean'],
                ],
            ],
            'classes' => self::simple(SchoolClass::class, 'classes'),
            'shifts' => self::simple(Shift::class, 'shifts'),
            'sections' => self::simple(Section::class, 'sections'),
            'groups' => self::simple(Group::class, 'groups'),
            'categories' => self::simple(Category::class, 'categories'),
            'subjects' => self::simple(Subject::class, 'subjects', ['code'], [
                'code' => ['nullable', 'string', 'max:50'],
            ]),
        ];
    }

    public static function find(string $resource): ?array
    {
        return self::all()[$resource] ?? null;
    }

    private static function simple(string $model, string $table, array $extraFields = [], array $extraRules = []): array
    {
        return [
            'model' => $model,
            'table' => $table,
            'fields' => array_merge(['name', 'name_bn'], $extraFields, ['serial', 'status']),
            'exclusive' => null,
            'extraRules' => $extraRules,
        ];
    }

    /** Validation rules for a resource, scoped so uniqueness is per-branch. */
    public static function rules(array $config, int $branchId, ?int $ignoreId): array
    {
        $unique = Rule::unique($config['table'], 'name')
            ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at'))
            ->ignore($ignoreId);

        return array_merge([
            'name' => ['required', 'string', 'max:150', $unique],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'serial' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ], $config['extraRules']);
    }
}
