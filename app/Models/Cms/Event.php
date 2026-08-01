<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Enums\Cms\ContentStatus;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublishing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property ContentStatus $status
 * @property \Illuminate\Support\Carbon|null $published_at
 */
class Event extends Model
{
    use BelongsToBranch;
    use HasFactory;
    use HasPublishing;
    use HasSlug;
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'body', 'starts_at', 'ends_at', 'location',
        'featured_asset_id', 'status', 'published_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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
    public function featuredAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'featured_asset_id');
    }

    /**
     * @return MorphToMany<Term, $this>
     */
    public function terms(): MorphToMany
    {
        return $this->morphToMany(Term::class, 'termable')->withTimestamps();
    }
}
