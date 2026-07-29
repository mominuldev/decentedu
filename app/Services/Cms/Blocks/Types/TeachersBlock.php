<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use App\Models\Hr\Employee;
use Illuminate\Validation\Rule;

class TeachersBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Teachers;
    }

    public function rules(): array
    {
        return [
            'subtitle' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'string', 'max:2048'],
            'cta_target' => ['nullable', Rule::in(['self', 'blank'])],
            'teachers_mode' => ['nullable', Rule::in(['active', 'latest', 'selected'])],
            'teachers_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'teacher_ids' => ['nullable', 'array'],
            'teacher_ids.*' => ['integer'],
            'heading_align' => ['nullable', Rule::in(['left', 'center', 'right'])],
            'layout' => ['nullable', Rule::in(['grid', 'list'])],
        ];
    }

    public function toResource(array $payload): array
    {
        $teachers = $this->fetchTeachers($payload);

        return [
            'subtitle' => $payload['subtitle'] ?? null,
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
            'cta_label' => $payload['cta_label'] ?? null,
            'cta_url' => $payload['cta_url'] ?? null,
            'cta_target' => $payload['cta_target'] ?? 'self',
            'teachers_mode' => $payload['teachers_mode'] ?? 'active',
            'teachers_limit' => (int) ($payload['teachers_limit'] ?? 12),
            'teacher_ids' => $payload['teacher_ids'] ?? [],
            'heading_align' => $payload['heading_align'] ?? 'left',
            'layout' => $payload['layout'] ?? 'grid',
            'teachers' => $teachers,
        ];
    }

    /**
     * Fetch teachers based on the configured mode
     */
    private function fetchTeachers(array $payload): array
    {
        $mode = $payload['teachers_mode'] ?? 'active';
        $limit = (int) ($payload['teachers_limit'] ?? 12);
        $teacherIds = $payload['teacher_ids'] ?? [];

        $query = Employee::with(['designation', 'hrSection'])
            ->whereHas('subjectTeachers') // Only teachers (employees with subject assignments)
            ->where('status', 'active');

        match ($mode) {
            'active' => $query->where('status', 'active'),
            'latest' => $query->orderBy('joining_date', 'desc'),
            'selected' => $query->whereIn('id', $teacherIds),
            default => $query->where('status', 'active'),
        };

        $teachers = $query->limit($limit)->get();

        return $teachers->map(function ($teacher) {
            // Resolve photo URL from Asset if photo_path is an Asset ID
            $photoUrl = null;
            if ($teacher->photo_path) {
                $asset = \App\Models\Cms\Asset::find($teacher->photo_path);
                if ($asset) {
                    $photoUrl = $this->getPublicAssetUrl($asset);
                }
            }

            return [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'name_bn' => $teacher->name_bn,
                'employee_uid' => $teacher->employee_uid,
                'photo_url' => $photoUrl,
                'photo_path' => $photoUrl,
                'designation' => $teacher->designation?->name,
                'hr_section' => $teacher->hrSection?->name,
                'employment_type' => $teacher->employment_type,
                'joining_date' => $teacher->joining_date?->format('Y-m-d'),
            ];
        })->toArray();
    }

    /**
     * Get public URL for an asset (for use in public site rendering)
     */
    private function getPublicAssetUrl(\App\Models\Cms\Asset $asset): ?string
    {
        if (!$asset->isPrivate()) {
            $payload = $asset->toApiPayload();
            return $payload['preview_url'] ?? $payload['url'] ?? null;
        }

        // For private assets, use the public site route
        return "/api/v1/site/media/{$asset->id}/file?conversion=preview";
    }
}
