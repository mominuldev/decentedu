<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class DividerBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Divider;
    }

    public function rules(): array
    {
        return [
            'style' => ['nullable', Rule::in(['line', 'space'])],
            'size' => ['nullable', Rule::in(['sm', 'md', 'lg'])],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'style' => $payload['style'] ?? 'line',
            'size' => $payload['size'] ?? 'md',
        ];
    }
}
