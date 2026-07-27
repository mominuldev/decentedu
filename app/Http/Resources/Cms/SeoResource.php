<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Effective SEO payload: the record's own SEO row merged over sensible
 * fallbacks derived from the content itself, so the frontend can render
 * tags without fallback logic.
 */
class SeoResource extends JsonResource
{
    public static function for(?Seo $seo, string $fallbackTitle, ?string $fallbackDescription = null): self
    {
        return (new self($seo))->additional([
            'fallback_title' => $fallbackTitle,
            'fallback_description' => $fallbackDescription,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Seo|null $seo */
        $seo = $this->resource;

        $fallbackTitle = $this->additional['fallback_title'] ?? '';
        $fallbackDescription = $this->additional['fallback_description'] ?? null;

        $title = $seo?->meta_title ?? (string) $fallbackTitle;
        $ogImage = $seo?->ogImageAsset ?? null;

        return [
            'meta_title' => $title,
            'meta_description' => $seo?->meta_description ?? $fallbackDescription,
            'canonical_url' => $seo?->canonical_url,
            'robots' => $seo?->robots?->value ?? 'index,follow',
            'og_title' => $seo?->og_title ?? $title,
            'og_description' => $seo?->og_description ?? $seo?->meta_description ?? $fallbackDescription,
            'og_image' => $ogImage?->toApiPayload(),
            'structured_data' => $seo?->structured_data,
        ];
    }
}
