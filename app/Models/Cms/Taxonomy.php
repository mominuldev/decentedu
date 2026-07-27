<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Taxonomy extends Model
{
    use BelongsToBranch;
    use HasFactory;
    use HasSlug;

    protected $fillable = ['name', 'slug', 'hierarchical', 'object_types'];

    protected function casts(): array
    {
        return [
            'hierarchical' => 'boolean',
            'object_types' => 'array',
        ];
    }

    /**
     * Local scope: taxonomies that apply to the given content type ($type), plus any that are
     * unscoped (object_types null = global).
     *
     * @param  Builder<Taxonomy>  $query
     */
    public function scopeForObjectType($query, string $type): void
    {
        $query->where(function ($q) use ($type): void {
            $q->whereNull('object_types')->orWhereJsonContains('object_types', $type);
        });
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * @return HasMany<Term, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('position');
    }
}
