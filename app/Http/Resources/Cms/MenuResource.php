<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Menu
 */
class MenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->items->where('is_visible', true)->groupBy('parent_id');

        $build = function (?int $parentId) use (&$build, $items): array {
            return $items->get($parentId, collect())
                ->sortBy('position')
                ->values()
                ->map(fn (MenuItem $item): array => [
                    'id' => $item->id,
                    'label' => $item->label,
                    'url' => $item->resolveUrl(),
                    'target' => $item->target,
                    'children' => $build($item->id),
                ])
                ->all();
        };

        return [
            'key' => $this->key,
            'name' => $this->name,
            'items' => $build(null),
        ];
    }
}
