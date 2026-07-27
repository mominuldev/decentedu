<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Models\Cms\Asset;
use App\Services\Cms\Blocks\BlockTypeContract;
use Illuminate\Support\Collection;

abstract class BaseBlockType implements BlockTypeContract
{
    public function supportsChildren(): bool
    {
        return false;
    }

    /**
     * Resolve a single asset ID from a payload to its API representation.
     *
     * @return array<string, mixed>|null
     */
    protected function assetPayload(mixed $id): ?array
    {
        if (! is_numeric($id)) {
            return null;
        }

        return Asset::query()->find((int) $id)?->toApiPayload();
    }

    /**
     * Resolve a list of asset IDs, preserving payload order.
     *
     * @param  array<int, mixed>  $ids
     * @return list<array<string, mixed>>
     */
    protected function assetsPayload(array $ids): array
    {
        $ids = array_values(array_map(intval(...), array_filter($ids, is_numeric(...))));

        /** @var Collection<int, Asset> $assets */
        $assets = Asset::query()->findMany($ids)->keyBy('id');

        return array_values(array_filter(array_map(
            fn (int $id): ?array => $assets->get($id)?->toApiPayload(),
            $ids,
        )));
    }
}
