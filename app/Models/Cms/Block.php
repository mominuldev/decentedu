<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Enums\Cms\BlockType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Block extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'payload', 'position', 'is_visible'];

    protected function casts(): array
    {
        return [
            'type' => BlockType::class,
            'payload' => 'array',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }
}
