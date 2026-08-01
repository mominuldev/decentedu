<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A file in the central media library. Exactly one underlying medialibrary
 * file per asset (singleFile collection); content references assets by FK
 * or by validated ID arrays inside block payloads.
 */
class Asset extends Model implements HasMedia
{
    use BelongsToBranch;
    use HasFactory;
    use InteractsWithMedia;

    public const COLLECTION = 'file';

    /** Categories stored off the public disk, served only through the authenticated route. */
    public const PRIVATE_CATEGORIES = ['photo', 'logo'];

    protected $fillable = ['name', 'alt_text', 'caption', 'media_folder_id', 'uploaded_by', 'category'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::COLLECTION)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Max, 300, 300)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->fit(Fit::Max, 1200, 1200)
            ->format('webp')
            ->withResponsiveImages();
    }

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'media_folder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia(self::COLLECTION);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->file()?->mime_type ?? '', 'image/');
    }

    public function isPrivate(): bool
    {
        return in_array($this->category, self::PRIVATE_CATEGORIES, true);
    }

    /**
     * The canonical shape used everywhere an asset appears in API output.
     *
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        $file = $this->file();
        $isSvg = $file?->mime_type === 'image/svg+xml';

        return [
            'id' => $this->id,
            'name' => $this->name,
            'alt' => $this->alt_text,
            'caption' => $this->caption,
            'mime_type' => $file?->mime_type,
            'size' => $file?->size,
            'url' => $this->urlFor(),
            'thumb_url' => $this->conversionUrl('thumb'),
            'preview_url' => $this->conversionUrl('preview'),
            'srcset' => ($this->hasConversion('preview') && ! $this->isPrivate() && ! $isSvg)
                ? ($file?->getSrcset('preview') ?: null)
                : null,
        ];
    }

    /**
     * Conversion URL for an image, falling back to the original file. Formats the image driver
     * cannot rasterise (SVG, or anything whose conversion has not run yet) have no derivative
     * on disk, so advertising one would hand the client a 404 image.
     */
    private function conversionUrl(string $conversion): ?string
    {
        if (! $this->isImage()) {
            return null;
        }

        if ($this->file()?->mime_type === 'image/svg+xml') {
            return $this->urlFor();
        }

        return $this->hasConversion($conversion) ? $this->urlFor($conversion) : $this->urlFor();
    }

    private function hasConversion(string $conversion): bool
    {
        return (bool) $this->file()?->hasGeneratedConversion($conversion);
    }

    /**
     * Private-category assets live on the auth-gated 'local' disk and have no public URL —
     * route through AssetController::serve() instead of Media::getUrl().
     */
    private function urlFor(?string $conversion = null): ?string
    {
        $file = $this->file();
        if (! $file) {
            return null;
        }

        if ($this->isPrivate()) {
            $query = $conversion ? '?conversion='.$conversion : '';

            return "/api/v1/cms/media/{$this->id}/file{$query}";
        }

        return $conversion ? $file->getUrl($conversion) : $file->getUrl();
    }
}
