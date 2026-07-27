<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class QuoteBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Quote;
    }

    public function rules(): array
    {
        return [
            'image_asset_id' => ['nullable', 'exists:assets,id'],
            'image_caption' => ['nullable', 'string', 'max:255'],
            'quote_message' => ['required', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'variant' => ['nullable', Rule::in(['default', 'full_details'])],
            'text_align' => ['nullable', Rule::in(['left', 'center', 'right'])],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'image_asset_id' => $payload['image_asset_id'] ?? null,
            'image_caption' => $payload['image_caption'] ?? null,
            'quote_message' => $payload['quote_message'] ?? null,
            'description' => $payload['description'] ?? null,
            'name' => $payload['name'] ?? null,
            'designation' => $payload['designation'] ?? null,
            'organization' => $payload['organization'] ?? null,
            'variant' => $payload['variant'] ?? 'default',
            'text_align' => $payload['text_align'] ?? 'center',
        ];
    }
}
