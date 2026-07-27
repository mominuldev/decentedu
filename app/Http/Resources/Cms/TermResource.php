<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\Term;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Term
 */
class TermResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'position' => $this->position,
            'image' => $this->featuredAsset?->toApiPayload(),
            'posts_count' => $this->whenCounted('posts'),
            'children' => TermResource::collection($this->whenLoaded('children')),
        ];
    }
}
