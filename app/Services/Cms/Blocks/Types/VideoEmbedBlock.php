<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class VideoEmbedBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::VideoEmbed;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'aspect_ratio' => ['nullable', Rule::in(['16:9', '4:3', '1:1', '9:16'])],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'url' => $payload['url'] ?? null,
            'title' => $payload['title'] ?? null,
            'aspect_ratio' => $payload['aspect_ratio'] ?? '16:9',
        ];
    }
}
