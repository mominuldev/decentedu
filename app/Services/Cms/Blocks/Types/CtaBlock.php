<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class CtaBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Cta;
    }

    public function rules(): array
    {
        return [
            'subtitle' => ['nullable', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'text' => ['nullable', 'string', 'max:1000'],
            'cta_primary_label' => ['nullable', 'string', 'max:100'],
            'cta_primary_url' => ['nullable', 'string', 'max:2048'],
            'cta_primary_target' => ['nullable', Rule::in(['self', 'blank'])],
            'cta_primary_variant' => ['nullable', Rule::in(['primary', 'secondary', 'outline', 'ghost'])],
            'cta_secondary_label' => ['nullable', 'string', 'max:100'],
            'cta_secondary_url' => ['nullable', 'string', 'max:2048'],
            'cta_secondary_target' => ['nullable', Rule::in(['self', 'blank'])],
            'cta_secondary_variant' => ['nullable', Rule::in(['primary', 'secondary', 'outline', 'ghost'])],
            'button_label' => ['nullable', 'string', 'max:100'],
            'button_url' => ['nullable', 'string', 'max:2048'],
            'button_target' => ['nullable', Rule::in(['self', 'blank'])],
            'variation' => ['nullable', 'string', Rule::in(['variation_one', 'variation_two', 'variation_three', 'variation_1', 'variation_2', 'variation_3'])],
            'items' => ['nullable', 'array'],
            'items.*.year' => ['required', 'string', 'max:50'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'quote_message' => ['nullable', 'string'],
            'author_image_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')],
            'name' => ['nullable', 'string', 'max:255'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'author_designation' => ['nullable', 'string', 'max:255'],
            'disclaimer' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toResource(array $payload): array
    {
        $title = $payload['title'] ?? $payload['heading'] ?? null;
        $description = $payload['description'] ?? $payload['text'] ?? null;
        $ctaPrimaryLabel = $payload['cta_primary_label'] ?? $payload['button_label'] ?? null;
        $ctaPrimaryUrl = $payload['cta_primary_url'] ?? $payload['button_url'] ?? null;
        $ctaPrimaryTarget = $payload['cta_primary_target'] ?? $payload['button_target'] ?? 'self';
        $ctaPrimaryVariant = $payload['cta_primary_variant'] ?? $payload['style'] ?? 'primary';
        $authorName = $payload['author_name'] ?? $payload['name'] ?? null;
        $authorDesignation = $payload['author_designation'] ?? $payload['designation'] ?? null;

        return [
            'variation' => $payload['variation'] ?? 'variation_one',
            'subtitle' => $payload['subtitle'] ?? null,
            'title' => $title,
            'heading' => $title,
            'description' => $description,
            'text' => $description,
            'cta_primary_label' => $ctaPrimaryLabel,
            'cta_primary_url' => $ctaPrimaryUrl,
            'cta_primary_target' => $ctaPrimaryTarget,
            'cta_primary_variant' => $ctaPrimaryVariant,
            'cta_secondary_label' => $payload['cta_secondary_label'] ?? null,
            'cta_secondary_url' => $payload['cta_secondary_url'] ?? null,
            'cta_secondary_target' => $payload['cta_secondary_target'] ?? 'self',
            'cta_secondary_variant' => $payload['cta_secondary_variant'] ?? 'secondary',
            'button_label' => $ctaPrimaryLabel,
            'button_url' => $ctaPrimaryUrl,
            'button_target' => $ctaPrimaryTarget,
            'style' => $ctaPrimaryVariant,
            'items' => array_values(array_map(
                fn (array $item): array => [
                    'year' => $item['year'] ?? '',
                    'title' => $item['title'] ?? '',
                ],
                $payload['items'] ?? [],
            )),
            'quote_message' => $payload['quote_message'] ?? null,
            'author_image' => $this->assetPayload($payload['author_image_asset_id'] ?? null),
            'author_name' => $authorName,
            'name' => $authorName,
            'author_designation' => $authorDesignation,
            'designation' => $authorDesignation,
            'disclaimer' => $payload['disclaimer'] ?? null,
        ];
    }
}
