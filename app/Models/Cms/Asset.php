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

    protected $fillable = ['name', 'alt_text', 'caption', 'media_folder_id', 'uploaded_by'];

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

    /**
     * The canonical shape used everywhere an asset appears in API output.
     *
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        $file = $this->file();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'alt' => $this->alt_text,
            'caption' => $this->caption,
            'mime_type' => $file?->mime_type,
            'size' => $file?->size,
            'url' => $file?->getUrl(),
            'thumb_url' => $this->isImage() ? $file?->getUrl('thumb') : null,
            'preview_url' => $this->isImage() ? $file?->getUrl('preview') : null,
            'srcset' => $this->isImage() ? ($file?->getSrcset('preview') ?: null) : null,
        ];
    }
}
