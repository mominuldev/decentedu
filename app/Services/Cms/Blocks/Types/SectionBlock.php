<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use App\Services\Cms\Blocks\BlockRegistry;
use Illuminate\Validation\Rule;

/**
 * A layout container block. Its children live in payload.blocks (same
 * shape as the top-level block list) and are validated/enriched
 * recursively by SyncBlocks/BlockAdminPresenter, not by this class.
 */
class SectionBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Section;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'tag' => ['required', 'string', Rule::in(['div', 'section', 'article', 'aside', 'header', 'footer'])],
            'margin_top' => ['nullable', 'integer', 'min:0', 'max:400'],
            'margin_right' => ['nullable', 'integer', 'min:0', 'max:400'],
            'margin_bottom' => ['nullable', 'integer', 'min:0', 'max:400'],
            'margin_left' => ['nullable', 'integer', 'min:0', 'max:400'],
            'padding_top' => ['nullable', 'integer', 'min:0', 'max:400'],
            'padding_right' => ['nullable', 'integer', 'min:0', 'max:400'],
            'padding_bottom' => ['nullable', 'integer', 'min:0', 'max:400'],
            'padding_left' => ['nullable', 'integer', 'min:0', 'max:400'],
            'background_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
        ];
    }

    public function supportsChildren(): bool
    {
        return true;
    }

    public function toResource(array $payload): array
    {
        /** @var BlockRegistry $registry */
        $registry = app(BlockRegistry::class);

        $rawChildren = is_array($payload['blocks'] ?? null) ? $payload['blocks'] : [];
        $children = [];

        foreach ($rawChildren as $child) {
            if (! is_array($child) || ! ($child['is_visible'] ?? true)) {
                continue;
            }

            $childType = $child['type'] ?? null;

            if (! is_string($childType) || ! $registry->has($childType)) {
                continue;
            }

            $children[] = [
                'type' => $childType,
                'data' => $registry->get($childType)->toResource(is_array($child['payload'] ?? null) ? $child['payload'] : []),
            ];
        }

        return [
            'name' => $payload['name'] ?? null,
            'tag' => $payload['tag'] ?? 'div',
            'margin' => [
                'top' => $payload['margin_top'] ?? 0,
                'right' => $payload['margin_right'] ?? 0,
                'bottom' => $payload['margin_bottom'] ?? 0,
                'left' => $payload['margin_left'] ?? 0,
            ],
            'padding' => [
                'top' => $payload['padding_top'] ?? 0,
                'right' => $payload['padding_right'] ?? 0,
                'bottom' => $payload['padding_bottom'] ?? 0,
                'left' => $payload['padding_left'] ?? 0,
            ],
            'background_color' => $payload['background_color'] ?? null,
            'blocks' => $children,
        ];
    }
}
