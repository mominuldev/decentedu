<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Page
 */
class PageListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'path' => $this->path,
            'template' => $this->template,
            'excerpt' => $this->excerpt,
            'parent_id' => $this->parent_id,
            'position' => $this->position,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
