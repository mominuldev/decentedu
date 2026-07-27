<?php

declare(strict_types=1);

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Term extends Model
{
    use HasFactory;
    use HasSlug;

    protected $fillable = ['taxonomy_id', 'name', 'slug', 'parent_id', 'description', 'position', 'featured_asset_id'];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->extraScope(fn ($builder) => $builder->where('taxonomy_id', $this->taxonomy_id));
    }

    /**
     * @return BelongsTo<Taxonomy, $this>
     */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'parent_id');
    }

    /**
     * @return HasMany<Term, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Term::class, 'parent_id')->orderBy('position');
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function featuredAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'featured_asset_id');
    }

    /**
     * @return MorphToMany<Post, $this>
     */
    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'termable');
    }
}
