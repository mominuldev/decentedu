<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class CardListBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::CardList;
    }

    public function rules(): array
    {
        return [
            'subtitle' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'heading_align' => ['nullable', Rule::in(['left', 'center', 'right'])],
            'layout' => ['nullable', Rule::in(['layout_one', 'layout_two', 'layout_three'])],
            'content_text_align' => ['nullable', Rule::in(['left', 'center', 'right'])],
            'items' => ['nullable', 'array'],
            'items.*.icon_asset_id' => ['nullable', 'integer'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.cta_label' => ['nullable', 'string', 'max:100'],
            'items.*.cta_url' => ['nullable', 'string', 'max:2048'],
            'items.*.cta_target' => ['nullable', Rule::in(['self', 'blank'])],
            'items.*.count' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function toResource(array $payload): array
    {
        $items = [];
        foreach (($payload['items'] ?? []) as $item) {
            $items[] = [
                'icon' => $this->assetPayload($item['icon_asset_id'] ?? null),
                'title' => $item['title'] ?? null,
                'description' => $item['description'] ?? null,
                'cta_label' => $item['cta_label'] ?? null,
                'cta_url' => $item['cta_url'] ?? null,
                'cta_target' => $item['cta_target'] ?? 'self',
                'count' => $item['count'] ?? null,
            ];
        }

        return [
            'subtitle' => $payload['subtitle'] ?? null,
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
            'heading_align' => $payload['heading_align'] ?? 'left',
            'layout' => $payload['layout'] ?? 'layout_one',
            'content_text_align' => $payload['content_text_align'] ?? 'left',
            'items' => $items,
        ];
    }
}
