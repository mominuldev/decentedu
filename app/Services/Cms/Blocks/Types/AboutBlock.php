<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class AboutBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::About;
    }

    public function rules(): array
    {
        return [
            'subtitle' => ['nullable', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'image_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')],
            'image_caption' => ['nullable', 'string', 'max:255'],
            'repeater_title' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'array'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.value' => ['required', 'string', 'max:255'],
        ];
    }

    public function toResource(array $payload): array
    {
        $title = $payload['title'] ?? $payload['heading'] ?? null;

        return [
            'subtitle' => $payload['subtitle'] ?? null,
            'title' => $title,
            'heading' => $title,
            'content' => $payload['content'] ?? null,
            'image' => $this->assetPayload($payload['image_asset_id'] ?? null),
            'image_caption' => $payload['image_caption'] ?? null,
            'repeater_title' => $payload['repeater_title'] ?? null,
            'items' => array_values(array_map(
                fn (array $item): array => [
                    'label' => $item['label'] ?? '',
                    'value' => $item['value'] ?? '',
                ],
                $payload['items'] ?? [],
            )),
        ];
    }
}
