<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Actions\Cms\Menus\SaveMenuTree;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\Menus\MenuRequest;
use App\Http\Requests\Cms\Menus\MenuTreeRequest;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Cms\Page;
use App\Models\Cms\Post;
use App\Models\Cms\Term;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        $menus = Menu::query()->withCount('items')->orderBy('name')->get();

        return ApiResponse::success($menus, 'Menus retrieved.');
    }

    public function store(MenuRequest $request): JsonResponse
    {
        $menu = Menu::query()->create($request->validated());

        return ApiResponse::success($menu, 'Menu created.', status: 201);
    }

    public function show(int $id): JsonResponse
    {
        $menu = Menu::query()->with(['items.linkable'])->findOrFail($id);

        return ApiResponse::success([
            'menu' => $menu->only(['id', 'name', 'key', 'is_active']),
            'items' => $this->buildTree($menu),
            'link_targets' => [
                'pages' => Page::query()->orderBy('path')->get(['id', 'title', 'path']),
                'posts' => Post::query()->orderByDesc('published_at')->limit(100)->get(['id', 'title', 'slug']),
                'terms' => Term::query()->whereHas('taxonomy')->orderBy('name')->get(['id', 'name', 'slug']),
            ],
        ], 'Menu retrieved.');
    }

    public function update(MenuRequest $request, int $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);
        $menu->update($request->validated());

        return ApiResponse::success($menu, 'Menu updated.');
    }

    public function updateTree(MenuTreeRequest $request, int $id, SaveMenuTree $action): JsonResponse
    {
        $menu = Menu::findOrFail($id);
        $action->handle($menu, $request->validated('items'));

        $menu->load(['items.linkable']);

        return ApiResponse::success($this->buildTree($menu), 'Menu items saved.');
    }

    public function destroy(int $id): JsonResponse
    {
        Menu::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Menu deleted.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTree(Menu $menu): array
    {
        $items = $menu->items->groupBy('parent_id');

        $build = function (?int $parentId) use (&$build, $items): array {
            return $items->get($parentId, collect())
                ->sortBy('position')
                ->values()
                ->map(fn (MenuItem $item): array => [
                    'id' => $item->id,
                    'label' => $item->label,
                    'linkable_type' => $item->linkable_type,
                    'linkable_id' => $item->linkable_id,
                    'url' => $item->url,
                    'target' => $item->target,
                    'is_visible' => $item->is_visible,
                    'resolved_url' => $item->resolveUrl(),
                    'children' => $build($item->id),
                ])
                ->all();
        };

        return $build(null);
    }
}
