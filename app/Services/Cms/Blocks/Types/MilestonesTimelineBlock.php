<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class MilestonesTimelineBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::MilestonesTimeline;
    }

    public function rules(): array
    {
        return [
            'subtitle' => ['nullable', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content_align' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'items' => ['nullable', 'array'],
            'items.*.year' => ['required', 'string', 'max:50'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
        ];
    }

    public function toResource(array $payload): array
    {
        $title = $payload['title'] ?? $payload['heading'] ?? null;

        return [
            'subtitle' => $payload['subtitle'] ?? null,
            'title' => $title,
            'heading' => $title,
            'description' => $payload['description'] ?? null,
            'content_align' => $payload['content_align'] ?? 'center',
            'items' => array_values(array_map(
                fn (array $item): array => [
                    'year' => $item['year'] ?? '',
                    'title' => $item['title'] ?? '',
                    'description' => $item['description'] ?? null,
                ],
                $payload['items'] ?? [],
            )),
        ];
    }
}
