<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;

class RichTextBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::RichText;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'content' => $payload['content'] ?? '',
        ];
    }
}
