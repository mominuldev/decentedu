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
            'variation' => ['nullable', 'string', Rule::in(['variation_one', 'variation_two', 'variation_three', 'variation_1', 'variation_2', 'variation_3'])],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'string', 'max:2048'],
            'cta_target' => ['nullable', Rule::in(['self', 'blank'])],
            'cta_variant' => ['nullable', Rule::in(['primary', 'secondary', 'outline', 'ghost'])],
            'quote_subtitle' => ['nullable', 'string', 'max:500'],
            'quote_message' => ['nullable', 'string'],
            'author' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toResource(array $payload): array
    {
        $title = $payload['title'] ?? $payload['heading'] ?? null;

        return [
            'variation' => $payload['variation'] ?? 'variation_one',
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
            'cta_label' => $payload['cta_label'] ?? null,
            'cta_url' => $payload['cta_url'] ?? null,
            'cta_target' => $payload['cta_target'] ?? 'self',
            'cta_variant' => $payload['cta_variant'] ?? 'primary',
            'quote_subtitle' => $payload['quote_subtitle'] ?? null,
            'quote_message' => $payload['quote_message'] ?? null,
            'author' => $payload['author'] ?? null,
            'designation' => $payload['designation'] ?? null,
        ];
    }
}
