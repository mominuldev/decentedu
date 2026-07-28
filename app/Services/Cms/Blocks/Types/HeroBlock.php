<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class HeroBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Hero;
    }

    public function rules(): array
    {
        return [
            'eiin' => ['nullable', 'string', 'max:50'],
            'heading' => ['required', 'string', 'max:255'],
            'highlight_heading' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'cta_one_label' => ['nullable', 'string', 'max:100'],
            'cta_one_url' => ['nullable', 'string', 'max:2048'],
            'cta_two_label' => ['nullable', 'string', 'max:100'],
            'cta_two_url' => ['nullable', 'string', 'max:2048'],
            'image_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')],
            'image_caption' => ['nullable', 'string', 'max:255'],
            'batch_text' => ['nullable', 'string', 'max:255'],
            'founding_period_text' => ['nullable', 'string', 'max:255'],
            'counters' => ['nullable', 'array'],
            'counters.*.title' => ['required', 'string', 'max:255'],
            'counters.*.subtitle' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'eiin' => $payload['eiin'] ?? null,
            'heading' => $payload['heading'] ?? null,
            'highlight_heading' => $payload['highlight_heading'] ?? null,
            'subtitle' => $payload['subtitle'] ?? null,
            'cta_one_label' => $payload['cta_one_label'] ?? null,
            'cta_one_url' => $payload['cta_one_url'] ?? null,
            'cta_two_label' => $payload['cta_two_label'] ?? null,
            'cta_two_url' => $payload['cta_two_url'] ?? null,
            'image' => $this->assetPayload($payload['image_asset_id'] ?? null),
            'image_caption' => $payload['image_caption'] ?? null,
            'batch_text' => $payload['batch_text'] ?? null,
            'founding_period_text' => $payload['founding_period_text'] ?? null,
            'counters' => array_values(array_map(
                fn (array $counter): array => [
                    'title' => $counter['title'] ?? '',
                    'subtitle' => $counter['subtitle'] ?? '',
                ],
                $payload['counters'] ?? [],
            )),
        ];
    }
}
