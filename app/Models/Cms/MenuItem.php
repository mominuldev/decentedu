<?php

declare(strict_types=1);

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id', 'parent_id', 'label', 'linkable_type', 'linkable_id',
        'url', 'target', 'is_visible', 'position',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('position');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Resolve the URL this item points at: the linked content's public
     * path when a linkable is set, the raw URL otherwise. Relies on the
     * linkable relation being eager-loaded by the caller.
     */
    public function resolveUrl(): ?string
    {
        return match (true) {
            $this->linkable instanceof Page => '/'.$this->linkable->path,
            $this->linkable instanceof Post => '/blog/'.$this->linkable->slug,
            $this->linkable instanceof Term => '/blog/category/'.$this->linkable->slug,
            default => $this->url,
        };
    }
}
