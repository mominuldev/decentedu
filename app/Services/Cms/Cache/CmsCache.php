<?php

declare(strict_types=1);

namespace App\Services\Cms\Cache;

use App\Support\BranchContext;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Single owner of every CMS cache key. All caching and invalidation goes
 * through this service so keys stay discoverable and driver-agnostic
 * (targeted forgets — no tags, no version bumping). Keys are prefixed with
 * the active branch id so cached menus/page paths never leak across branches.
 */
class CmsCache
{
    private const MENU_PREFIX = 'cms.menu.';

    private const PAGE_PATH_PREFIX = 'cms.page.path.';

    private const PAGE_TTL = 3600;

    public function __construct(private readonly BranchContext $context) {}

    private function branchScope(): string
    {
        return 'b'.($this->context->id() ?? 0).'.';
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function rememberMenu(string $key, Closure $callback): mixed
    {
        return Cache::rememberForever(self::MENU_PREFIX.$this->branchScope().$key, $callback);
    }

    public function forgetMenu(string $key): void
    {
        Cache::forget(self::MENU_PREFIX.$this->branchScope().$key);
    }

    /**
     * @param  iterable<int, string>  $keys
     */
    public function forgetMenus(iterable $keys): void
    {
        foreach ($keys as $key) {
            $this->forgetMenu($key);
        }
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function rememberPagePath(string $path, Closure $callback): mixed
    {
        return Cache::remember(self::PAGE_PATH_PREFIX.$this->branchScope().sha1($path), self::PAGE_TTL, $callback);
    }

    public function forgetPagePath(string $path): void
    {
        Cache::forget(self::PAGE_PATH_PREFIX.$this->branchScope().sha1($path));
    }

    /**
     * @param  iterable<int, string>  $paths
     */
    public function forgetPagePaths(iterable $paths): void
    {
        foreach ($paths as $path) {
            $this->forgetPagePath($path);
        }
    }
}
