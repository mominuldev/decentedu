<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Enums\Cms\RobotsDirective;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Seo extends Model
{
    protected $table = 'seo';

    protected $fillable = [
        'meta_title', 'meta_description', 'canonical_url', 'robots',
        'og_title', 'og_description', 'og_image_asset_id', 'structured_data',
    ];

    protected function casts(): array
    {
        return [
            'robots' => RobotsDirective::class,
            'structured_data' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function ogImageAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'og_image_asset_id');
    }
}
