<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Cms\Block;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasBlocks
{
    /**
     * @return MorphMany<Block, $this>
     */
    public function blocks(): MorphMany
    {
        return $this->morphMany(Block::class, 'blockable')->orderBy('position');
    }
}
