<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_uid' => $this->employee_uid,
            'name' => $this->name,
            'name_bn' => $this->name_bn,
            'sex' => $this->sex,
            'religion' => $this->religion,
            'blood_group' => $this->blood_group,
            'dob' => $this->dob?->toIso8601String(),
            'mobile' => $this->mobile,
            'email' => $this->email,
            'nid' => $this->nid,
            'photo_url' => $this->photoUrl(),
            'photo_path' => $this->photoUrl(),
            'present_address' => $this->present_address,
            'permanent_address' => $this->permanent_address,
            'joining_date' => $this->joining_date?->toIso8601String(),
            'leaving_date' => $this->leaving_date?->toIso8601String(),
            'employment_type' => $this->employment_type,
            'status' => $this->status,
            'qualifications' => $this->qualifications,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'designation' => $this->when(
                $this->relationLoaded('designation') && $this->designation,
                fn () => [
                    'id' => $this->designation->id,
                    'name' => $this->designation->name,
                ]
            ),

            'hr_section' => $this->when(
                $this->relationLoaded('hrSection') && $this->hrSection,
                fn () => [
                    'id' => $this->hrSection->id,
                    'name' => $this->hrSection->name,
                ]
            ),

            'subject_teachers' => $this->when(
                $this->relationLoaded('subjectTeachers'),
                fn () => SubjectTeacherResource::collection($this->subjectTeachers),
            ),
        ];
    }

    /**
     * Resolve the photo URL from Asset if photo_path is an Asset ID
     */
    private function photoUrl(): ?string
    {
        if (empty($this->photo_path)) {
            return null;
        }

        $asset = \App\Models\Cms\Asset::find($this->photo_path);
        if (!$asset) {
            return null;
        }

        // For private assets (photos, logos), use the public site route
        if ($asset->isPrivate()) {
            return "/api/v1/site/media/{$asset->id}/file?conversion=preview";
        }

        // For public assets, use the direct URL
        $payload = $asset->toApiPayload();
        return $payload['preview_url'] ?? $payload['url'] ?? null;
    }
}
