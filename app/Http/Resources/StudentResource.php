<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
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
            'student_uid' => $this->student_uid,
            'name' => $this->name,
            'name_bn' => $this->name_bn,
            'sex' => $this->sex,
            'religion' => $this->religion,
            'blood_group' => $this->blood_group,
            'dob' => $this->dob?->toISOString(),
            'fathers_name' => $this->fathers_name,
            'mothers_name' => $this->mothers_name,
            'mobile' => $this->mobile,
            'father_mobile' => $this->father_mobile,
            'mother_mobile' => $this->mother_mobile,
            'photo_path' => $this->photo_path,
            'present_address' => $this->present_address,
            'permanent_address' => $this->permanent_address,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships — values are closures so they're only evaluated (and only touch the
            // relation) when the when() condition is true; a plain value is evaluated eagerly by
            // PHP regardless of the condition, which both NPEs on a null relation and defeats
            // relationLoaded() checks by triggering a lazy load of an unrequested relation.
            'current_enrollment' => $this->when(
                $this->relationLoaded('currentEnrollment') && $this->currentEnrollment,
                fn () => [
                    'id' => $this->currentEnrollment->id,
                    'academic_year_id' => $this->currentEnrollment->academic_year_id,
                    'class_config_id' => $this->currentEnrollment->class_config_id,
                    'group_id' => $this->currentEnrollment->group_id,
                    'category_id' => $this->currentEnrollment->category_id,
                    'roll' => $this->currentEnrollment->roll,
                    'is_current' => $this->currentEnrollment->is_current,
                    'enrolled_at' => $this->currentEnrollment->enrolled_at?->toISOString(),
                    'class_config' => $this->when(
                        $this->currentEnrollment->relationLoaded('classConfig') && $this->currentEnrollment->classConfig,
                        fn () => [
                            'id' => $this->currentEnrollment->classConfig->id,
                            'name' => $this->currentEnrollment->classConfig->name,
                        ],
                    ),
                ],
            ),

            'guardians' => GuardianResource::collection($this->when($this->relationLoaded('guardians'), fn () => $this->guardians)),
            'documents' => DocumentResource::collection($this->when($this->relationLoaded('documents'), fn () => $this->documents)),
        ];
    }
}