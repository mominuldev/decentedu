<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class GalleryBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Gallery;
    }

    public function rules(): array
    {
        return [
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['integer', Rule::exists('assets', 'id')],
            'columns' => ['nullable', 'integer', 'between:1,6'],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'images' => $this->assetsPayload($payload['asset_ids'] ?? []),
            'columns' => (int) ($payload['columns'] ?? 3),
        ];
    }
}
