<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Page
 */
class PageResource extends JsonResource
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
            'published_at' => $this->published_at?->toIso8601String(),
            'featured_image' => $this->featuredAsset?->toApiPayload(),
            'breadcrumbs' => $this->breadcrumbs(),
            'blocks' => BlockResource::collection(
                $this->whenLoaded('blocks', fn () => $this->blocks->where('is_visible', true)->values()),
            ),
            'seo' => SeoResource::for($this->seo, $this->title, $this->excerpt),
            'children' => $this->whenLoaded('children', fn () => $this->children
                ->map(fn (Page $child): array => [
                    'id' => $child->id,
                    'title' => $child->title,
                    'path' => $child->path,
                    'excerpt' => $child->excerpt,
                ])
                ->all()),
        ];
    }

    /**
     * @return list<array{title: string, path: string}>
     */
    private function breadcrumbs(): array
    {
        $crumbs = $this->ancestorCrumbs();
        $crumbs[] = ['title' => $this->title, 'path' => $this->path];

        return $crumbs;
    }
}
