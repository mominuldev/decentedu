<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks;

use App\Models\Cms\Asset;
use App\Models\Cms\Block;
use Illuminate\Support\Collection;

/**
 * Shapes blocks for the admin BlockEditor: raw payloads enriched with
 * asset preview objects so pickers can render the current selection.
 */
class BlockAdminPresenter
{
    /**
     * @param  Collection<int, Block>  $blocks
     * @return list<array<string, mixed>>
     */
    public function present(Collection $blocks): array
    {
        return $blocks
            ->map(fn (Block $block): array => [
                'id' => $block->id,
                'type' => $block->type->value,
                'payload' => $this->enrich($block->type->value, $block->payload ?? []),
                'is_visible' => $block->is_visible,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enrich(string $blockType, array $payload): array
    {
        foreach (['image_asset_id', 'asset_id'] as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                $payload["{$key}_preview"] = Asset::query()->find((int) $payload[$key])?->toApiPayload();
            }
        }

        if (isset($payload['asset_ids']) && is_array($payload['asset_ids'])) {
            $ids = array_values(array_map(intval(...), array_filter($payload['asset_ids'], is_numeric(...))));
            $assets = Asset::query()->findMany($ids)->keyBy('id');

            $payload['asset_previews'] = array_values(array_filter(array_map(
                fn (int $id): ?array => $assets->get($id)?->toApiPayload(),
                $ids,
            )));
        }

        if (isset($payload['blocks']) && is_array($payload['blocks'])) {
            $payload['blocks'] = array_map(
                fn (array $child): array => [
                    'id' => null,
                    'type' => $child['type'] ?? null,
                    'payload' => $this->enrich($child['type'] ?? '', is_array($child['payload'] ?? null) ? $child['payload'] : []),
                    'is_visible' => $child['is_visible'] ?? true,
                ],
                $payload['blocks'],
            );
        }

        // Add defaults for notice_board block only
        if ($blockType === 'notice_board') {
            $payload['notices_mode'] ??= 'latest';
            $payload['events_mode'] ??= 'upcoming';
            $payload['notices_limit'] ??= 5;
            $payload['events_limit'] ??= 5;
        }

        // Add defaults for quote block only
        if ($blockType === 'quote') {
            $payload['variant'] ??= 'default';
            $payload['text_align'] ??= 'center';
        }

        // Add defaults for gallery block
        if ($blockType === 'gallery') {
            $payload['text_align'] ??= 'left';
        }

        return $payload;
    }
}
