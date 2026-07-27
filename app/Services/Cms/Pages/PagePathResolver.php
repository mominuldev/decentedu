<?php

declare(strict_types=1);

namespace App\Services\Cms\Pages;

use App\Models\Cms\Page;
use App\Services\Cms\Cache\CmsCache;

class PagePathResolver
{
    public function __construct(private readonly CmsCache $cache) {}

    /**
     * Resolve a public URL path ("about/team") to its published page.
     * The path→id lookup is cached (per branch); the page itself is loaded
     * fresh so relations and publishing state are never stale.
     */
    public function resolve(string $path): ?Page
    {
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        $id = $this->cache->rememberPagePath(
            $path,
            fn (): int => (int) Page::query()->where('path', $path)->value('id'),
        );

        if ($id === 0) {
            return null;
        }

        return Page::query()
            ->published()
            ->with(['seo.ogImageAsset', 'featuredAsset'])
            ->find($id);
    }
}
