<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class ImageBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Image;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', Rule::exists('assets', 'id')],
            'caption' => ['nullable', 'string', 'max:500'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'alignment' => ['nullable', Rule::in(['left', 'center', 'right', 'full'])],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'image' => $this->assetPayload($payload['asset_id'] ?? null),
            'caption' => $payload['caption'] ?? null,
            'link_url' => $payload['link_url'] ?? null,
            'alignment' => $payload['alignment'] ?? 'full',
        ];
    }
}
