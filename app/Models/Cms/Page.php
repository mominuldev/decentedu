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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 */
class Page extends Model
{
    use BelongsToBranch;
    use HasBlocks;
    use HasFactory;
    use HasPublishing;
    use HasSeo;
    use HasSlug;
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'parent_id', 'template', 'excerpt', 'status',
        'published_at', 'position', 'featured_asset_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->extraScope(fn ($builder) => $builder->where('parent_id', $this->parent_id));
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('position');
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function featuredAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'featured_asset_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The public URL path for this page, derived from its ancestry. Walks
     * ancestors by query (not relation traversal) so it stays safe under
     * preventLazyLoading and reflects unsaved parent_id changes.
     */
    public function computePath(): string
    {
        $segments = [$this->slug];
        $parentId = $this->parent_id;

        while ($parentId !== null) {
            $parent = static::query()->select(['id', 'slug', 'parent_id'])->find($parentId);

            if ($parent === null) {
                break;
            }

            array_unshift($segments, $parent->slug);
            $parentId = $parent->parent_id;
        }

        return implode('/', $segments);
    }

    /**
     * Ancestor chain (root-first, excluding self), fetched without lazy loading.
     *
     * @return list<array{title: string, path: string}>
     */
    public function ancestorCrumbs(): array
    {
        $crumbs = [];
        $parentId = $this->parent_id;

        while ($parentId !== null) {
            $parent = static::query()->select(['id', 'title', 'path', 'parent_id'])->find($parentId);

            if ($parent === null) {
                break;
            }

            array_unshift($crumbs, ['title' => $parent->title, 'path' => $parent->path]);
            $parentId = $parent->parent_id;
        }

        return $crumbs;
    }
}
