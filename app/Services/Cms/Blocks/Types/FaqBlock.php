<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;

class FaqBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Faq;
    }

    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.question' => ['required', 'string', 'max:500'],
            'items.*.answer' => ['required', 'string'],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'heading' => $payload['heading'] ?? null,
            'items' => array_values(array_map(
                fn (array $item): array => [
                    'question' => $item['question'] ?? '',
                    'answer' => $item['answer'] ?? '',
                ],
                $payload['items'] ?? [],
            )),
        ];
    }
}
