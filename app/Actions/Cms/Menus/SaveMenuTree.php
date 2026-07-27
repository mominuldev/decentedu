<?php

declare(strict_types=1);

namespace App\Actions\Cms\Menus;

use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use Illuminate\Support\Facades\DB;

/**
 * Persists the full nested item tree of a menu in one call. Existing
 * items are kept (matched by id), removed items are deleted, order and
 * nesting follow the submitted structure.
 */
class SaveMenuTree
{
    /**
     * @param  list<array<string, mixed>>  $items  Nested: each item may carry a "children" list.
     */
    public function handle(Menu $menu, array $items): void
    {
        DB::transaction(function () use ($menu, $items): void {
            $keptIds = $this->syncLevel($menu, $items, null);

            $menu->items()->whereKeyNot($keptIds)->delete();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<int>
     */
    private function syncLevel(Menu $menu, array $items, ?int $parentId): array
    {
        $keptIds = [];

        foreach (array_values($items) as $position => $data) {
            $attributes = [
                'parent_id' => $parentId,
                'label' => $data['label'],
                'linkable_type' => $data['linkable_type'] ?? null,
                'linkable_id' => $data['linkable_id'] ?? null,
                'url' => $data['url'] ?? null,
                'target' => $data['target'] ?? '_self',
                'is_visible' => $data['is_visible'] ?? true,
                'position' => $position,
            ];

            $existing = isset($data['id']) ? $menu->items()->find($data['id']) : null;

            if ($existing instanceof MenuItem) {
                $existing->update($attributes);
                $item = $existing;
            } else {
                $item = $menu->items()->create($attributes);
            }

            $keptIds[] = $item->id;

            $children = $data['children'] ?? [];

            if (is_array($children) && $children !== []) {
                $keptIds = array_merge($keptIds, $this->syncLevel($menu, $children, $item->id));
            }
        }

        return $keptIds;
    }
}
