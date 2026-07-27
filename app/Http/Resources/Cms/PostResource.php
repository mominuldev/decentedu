<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\Post;
use App\Models\Cms\Term;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
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
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'published_at' => $this->published_at?->toIso8601String(),
            'is_featured' => $this->is_featured,
            'reading_time' => $this->reading_time,
            'featured_image' => $this->featuredAsset?->toApiPayload(),
            'author' => $this->author !== null ? ['id' => $this->author->id, 'name' => $this->author->name] : null,
            'categories' => $this->whenLoaded('terms', fn () => $this->terms
                ->map(fn (Term $term): array => ['id' => $term->id, 'name' => $term->name, 'slug' => $term->slug])
                ->all()),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->all()),
            'blocks' => BlockResource::collection(
                $this->whenLoaded('blocks', fn () => $this->blocks->where('is_visible', true)->values()),
            ),
            'seo' => SeoResource::for($this->seo, $this->title, $this->excerpt),
        ];
    }
}
