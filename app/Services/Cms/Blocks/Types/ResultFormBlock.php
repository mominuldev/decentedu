<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;

/**
 * Section heading for the public student result search form. The actual
 * search/lookup is rendered client-side against the site/results API;
 * this block only supplies the surrounding subtitle/title/description copy.
 */
class ResultFormBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::ResultForm;
    }

    public function rules(): array
    {
        return [
            'subtitle' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'subtitle' => $payload['subtitle'] ?? null,
            'title' => $payload['title'] ?? 'Student Result Search',
            'description' => $payload['description'] ?? null,
        ];
    }
}
