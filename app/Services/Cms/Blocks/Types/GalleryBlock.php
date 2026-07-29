<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks\Types;

use App\Enums\Cms\BlockType;
use Illuminate\Validation\Rule;

class GalleryBlock extends BaseBlockType
{
    public function type(): BlockType
    {
        return BlockType::Gallery;
    }

    public function rules(): array
    {
        return [
            'subtitle' => ['nullable', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'string', 'max:2048'],
            'mode' => ['nullable', 'string', 'in:recent,selected'],
            'limit' => ['nullable', 'integer', 'min:1'],
            'gallery_ids' => ['nullable', 'array'],
            'gallery_ids.*' => ['integer', Rule::exists('galleries', 'id')],
            'asset_ids' => ['nullable', 'array'],
            'asset_ids.*' => ['integer', Rule::exists('assets', 'id')],
            'columns' => ['nullable', 'integer', 'between:1,6'],
        ];
    }

    public function toResource(array $payload): array
    {
        $galleriesData = [];
        $mode = $payload['mode'] ?? 'recent';

        if ($mode === 'selected' && !empty($payload['gallery_ids'])) {
            $galleries = \App\Models\Cms\Gallery::with(['coverAsset.media'])->whereIn('id', $payload['gallery_ids'])->get();
            $galleriesData = collect($payload['gallery_ids'])->map(function($id) use ($galleries) {
                return $galleries->firstWhere('id', $id);
            })->filter()->map->toApiPayload()->values()->all();
        } else {
            $limit = (int) ($payload['limit'] ?? 4);
            $galleries = \App\Models\Cms\Gallery::with(['coverAsset.media'])->latest()->limit($limit)->get();
            $galleriesData = $galleries->map->toApiPayload()->all();
        }

        return [
            'subtitle' => $payload['subtitle'] ?? null,
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
            'cta_label' => $payload['cta_label'] ?? null,
            'cta_url' => $payload['cta_url'] ?? null,
            'mode' => $mode,
            'limit' => $payload['limit'] ?? 4,
            'gallery_ids' => $payload['gallery_ids'] ?? [],
            'galleries' => $galleriesData,
            'images' => $this->assetsPayload($payload['asset_ids'] ?? []),
            'columns' => (int) ($payload['columns'] ?? 3),
        ];
    }
}
