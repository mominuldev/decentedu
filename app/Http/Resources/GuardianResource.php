<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuardianResource extends JsonResource
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
            'relationship' => $this->relationship,
            'name' => $this->name,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'address' => $this->address,
            'photo_path' => $this->photo_path,
            'occupation' => $this->occupation,
            'nid' => $this->nid,
            'is_emergency_contact' => $this->is_emergency_contact,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}