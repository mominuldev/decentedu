<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

/**
 * A page-top header: heading, optional description, and optional
 * breadcrumb trail, with per-element color/typography controls.
 */
class PageHeaderBlock extends BaseBlockType
{
    private const COLOR_REGEX = 'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/';

    private const FONT_WEIGHTS = ['normal', 'medium', 'semibold', 'bold'];

    private const TEXT_TRANSFORMS = ['none', 'uppercase', 'capitalize', 'lowercase'];

    public function type(): BlockType
    {
        return BlockType::PageHeader;
    }

    public function rules(): array
    {
        return [
            'subtitle' => ['required', 'string', 'max:30'],
            'heading' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'description_enabled' => ['boolean'],
            'breadcrumb_enabled' => ['boolean'],

            ...$this->typographyRules('title_'),
            ...$this->typographyRules('description_'),
            ...$this->typographyRules('breadcrumb_'),
            'breadcrumb_link_hover_color' => ['nullable', self::COLOR_REGEX],
            'breadcrumb_link_active_color' => ['nullable', self::COLOR_REGEX],

            'margin_top' => ['nullable', 'integer', 'min:0', 'max:400'],
            'margin_right' => ['nullable', 'integer', 'min:0', 'max:400'],
            'margin_bottom' => ['nullable', 'integer', 'min:0', 'max:400'],
            'margin_left' => ['nullable', 'integer', 'min:0', 'max:400'],
            'padding_top' => ['nullable', 'integer', 'min:0', 'max:400'],
            'padding_right' => ['nullable', 'integer', 'min:0', 'max:400'],
            'padding_bottom' => ['nullable', 'integer', 'min:0', 'max:400'],
            'padding_left' => ['nullable', 'integer', 'min:0', 'max:400'],
        ];
    }

    public function toResource(array $payload): array
    {
        return [
            'subtitle' => $payload['subtitle'] ?? null,
            'heading' => $payload['heading'] ?? null,
            'description' => $payload['description'] ?? null,
            'description_enabled' => (bool) ($payload['description_enabled'] ?? true),
            'breadcrumb_enabled' => (bool) ($payload['breadcrumb_enabled'] ?? true),

            'title_style' => $this->typographyResource($payload, 'title_'),
            'description_style' => $this->typographyResource($payload, 'description_'),
            'breadcrumb_style' => [
                ...$this->typographyResource($payload, 'breadcrumb_'),
                'link_hover_color' => $payload['breadcrumb_link_hover_color'] ?? null,
                'link_active_color' => $payload['breadcrumb_link_active_color'] ?? null,
            ],

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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function typographyRules(string $prefix): array
    {
        return [
            "{$prefix}color" => ['nullable', self::COLOR_REGEX],
            "{$prefix}font_size" => ['nullable', 'integer', 'min:8', 'max:120'],
            "{$prefix}font_weight" => ['nullable', Rule::in(self::FONT_WEIGHTS)],
            "{$prefix}text_transform" => ['nullable', Rule::in(self::TEXT_TRANSFORMS)],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function typographyResource(array $payload, string $prefix): array
    {
        return [
            'color' => $payload["{$prefix}color"] ?? null,
            'font_size' => $payload["{$prefix}font_size"] ?? null,
            'font_weight' => $payload["{$prefix}font_weight"] ?? 'normal',
            'text_transform' => $payload["{$prefix}text_transform"] ?? 'none',
        ];
    }
}
