<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class HeadingBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Heading;
    }

    public function rules(): array
    {
        return [
            'subtitle' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'string', 'max:2048'],
            'cta_variant' => ['nullable', Rule::in(['primary', 'secondary', 'outline', 'ghost'])],
            'cta_target' => ['nullable', Rule::in(['self', 'blank'])],
            'text_align' => ['nullable', Rule::in(['left', 'center', 'right'])],
            'layout' => ['nullable', Rule::in(['stacked', 'split'])],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'subtitle' => $payload['subtitle'] ?? null,
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
            'cta_label' => $payload['cta_label'] ?? null,
            'cta_url' => $payload['cta_url'] ?? null,
            'cta_variant' => $payload['cta_variant'] ?? 'primary',
            'cta_target' => $payload['cta_target'] ?? 'self',
            'text_align' => $payload['text_align'] ?? 'left',
            'layout' => $payload['layout'] ?? 'stacked',
        ];
    }
}
