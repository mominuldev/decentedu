<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cms\MenuResource;
use App\Models\Cms\Menu;
use App\Services\Cms\Cache\CmsCache;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    public function show(string $key, CmsCache $cache): JsonResponse
    {
        $payload = $cache->rememberMenu($key, function () use ($key): ?array {
            $menu = Menu::query()
                ->where('key', $key)
                ->where('is_active', true)
                ->with('items.linkable')
                ->first();

            return $menu !== null ? (new MenuResource($menu))->resolve() : null;
        });

        if ($payload === null) {
            abort(404, 'Menu not found.');
        }

        return ApiResponse::success($payload, 'Menu retrieved.');
    }
}
