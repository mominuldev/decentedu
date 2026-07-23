<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectTeacherResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'subject_id' => $this->subject_id,
            'class_config_id' => $this->class_config_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),

            // Relationships
            'subject' => $this->when($this->relationLoaded('subject') && $this->subject, [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
            ]),

            'class_config' => $this->when($this->relationLoaded('classConfig') && $this->classConfig, [
                'id' => $this->classConfig->id,
                'name' => $this->classConfig->name,
            ]),
        ];
    }
}