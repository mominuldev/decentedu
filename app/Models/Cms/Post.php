<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Enums\Cms\ContentStatus;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasBlocks;
use App\Models\Concerns\HasPublishing;
use App\Models\Concerns\HasSeo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Tags\HasTags;

/**
 * @property ContentStatus $status
 * @property \Illuminate\Support\Carbon|null $published_at
 */
class Post extends Model
{
    use BelongsToBranch;
    use HasBlocks;
    use HasFactory;
    use HasPublishing;
    use HasSeo;
    use HasSlug;
    use HasTags;
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'author_id', 'status', 'published_at',
        'is_featured', 'reading_time', 'featured_asset_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
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
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
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

    public function computeReadingTime(): int
    {
        $words = str_word_count(strip_tags($this->body ?? ''));

        return max(1, (int) ceil($words / 200));
    }
}
