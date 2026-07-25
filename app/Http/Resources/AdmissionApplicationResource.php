<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_year_id' => $this->admission_year_id,
            'class_config_id' => $this->class_config_id,
            'quota_id' => $this->quota_id,
            'application_no' => $this->application_no,
            'name' => $this->name,
            'name_bn' => $this->name_bn,
            'sex' => $this->sex,
            'religion' => $this->religion,
            'blood_group' => $this->blood_group,
            'dob' => $this->dob?->toISOString(),
            'fathers_name' => $this->fathers_name,
            'mothers_name' => $this->mothers_name,
            'mobile' => $this->mobile,
            'guardian_mobile' => $this->guardian_mobile,
            'photo_path' => $this->photo_path,
            'present_address' => $this->present_address,
            'permanent_address' => $this->permanent_address,
            'score' => $this->score !== null ? (float) $this->score : null,
            'status' => $this->status,
            'student_id' => $this->student_id,
            'applied_at' => $this->applied_at?->toISOString(),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'admission_year' => $this->when(
                $this->relationLoaded('admissionYear') && $this->admissionYear,
                fn () => [
                    'id' => $this->admissionYear->id,
                    'title' => $this->admissionYear->title,
                    'status' => $this->admissionYear->status,
                ],
            ),
            'class_config' => $this->when(
                $this->relationLoaded('classConfig') && $this->classConfig,
                fn () => [
                    'id' => $this->classConfig->id,
                    'name' => $this->classConfig->label(),
                ],
            ),
            'quota' => $this->when(
                $this->relationLoaded('quota') && $this->quota,
                fn () => [
                    'id' => $this->quota->id,
                    'name' => $this->quota->name,
                ],
            ),
        ];
    }
}
