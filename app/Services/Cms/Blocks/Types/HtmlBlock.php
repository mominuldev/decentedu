<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;

class HtmlBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Html;
    }

    public function rules(): array
    {
        return [
            'html' => ['required', 'string'],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'html' => $payload['html'] ?? '',
        ];
    }
}
