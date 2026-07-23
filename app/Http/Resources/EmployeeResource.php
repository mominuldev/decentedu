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
            'dob' => $this->dob?->toISOString(),
            'mobile' => $this->mobile,
            'email' => $this->email,
            'nid' => $this->nid,
            'photo_path' => $this->photo_path,
            'present_address' => $this->present_address,
            'permanent_address' => $this->permanent_address,
            'joining_date' => $this->joining_date?->toISOString(),
            'leaving_date' => $this->leaving_date?->toISOString(),
            'employment_type' => $this->employment_type,
            'status' => $this->status,
            'qualifications' => $this->qualifications,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'designation' => $this->when($this->relationLoaded('designation') && $this->designation, [
                'id' => $this->designation->id,
                'name' => $this->designation->name,
            ]),

            'hr_section' => $this->when($this->relationLoaded('hrSection') && $this->hrSection, [
                'id' => $this->hrSection->id,
                'name' => $this->hrSection->name,
            ]),

            'subject_teachers' => SubjectTeacherResource::collection($this->when($this->relationLoaded('subjectTeachers'), $this->subjectTeachers)),
        ];
    }
}