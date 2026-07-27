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

class Notice extends Model
{
    use BelongsToBranch;
    use HasFactory;
    use HasPublishing;
    use HasSlug;
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'body', 'notice_date', 'attachment_asset_id',
        'is_important', 'status', 'published_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'notice_date' => 'date',
            'published_at' => 'datetime',
            'is_important' => 'boolean',
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
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'attachment_asset_id');
    }

    /**
     * @return MorphToMany<Term, $this>
     */
    public function terms(): MorphToMany
    {
        return $this->morphToMany(Term::class, 'termable')->withTimestamps();
    }
}
