<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class CtaBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Cta;
    }

    public function rules(): array
    {
        return [
            'heading' => ['required', 'string', 'max:255'],
            'text' => ['nullable', 'string', 'max:1000'],
            'button_label' => ['required', 'string', 'max:100'],
            'button_url' => ['required', 'string', 'max:2048'],
            'style' => ['nullable', Rule::in(['primary', 'secondary', 'outline'])],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'heading' => $payload['heading'] ?? null,
            'text' => $payload['text'] ?? null,
            'button_label' => $payload['button_label'] ?? null,
            'button_url' => $payload['button_url'] ?? null,
            'style' => $payload['style'] ?? 'primary',
        ];
    }
}
