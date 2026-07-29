<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Enums\Cms\ContentStatus;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublishing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Gallery extends Model
{
    use BelongsToBranch;
    use HasFactory;
    use HasPublishing;
    use HasSlug;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_asset_id',
        'images',
        'status',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function coverAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'cover_asset_id');
    }

    public function toApiPayload(): array
    {
        $coverAsset = $this->relationLoaded('coverAsset')
            ? $this->coverAsset?->toApiPayload()
            : ($this->cover_asset_id ? Asset::query()->with('media')->find($this->cover_asset_id)?->toApiPayload() : null);

        $imageIds = is_array($this->images) ? array_map('intval', array_filter($this->images)) : [];
        $galleryAssets = !empty($imageIds)
            ? Asset::query()->with('media')->whereIn('id', $imageIds)->get()->map(fn (Asset $a) => $a->toApiPayload())->values()->all()
            : [];

        $statusStr = $this->status instanceof ContentStatus
            ? $this->status->value
            : (is_string($this->status) ? $this->status : 'published');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_asset_id' => $this->cover_asset_id,
            'cover_asset' => $coverAsset,
            'image_ids' => $imageIds,
            'images' => $galleryAssets,
            'status' => $statusStr,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
