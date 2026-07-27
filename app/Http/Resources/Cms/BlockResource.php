<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\Block;
use App\Services\Cms\Blocks\BlockRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Block
 */
class BlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $registry = app(BlockRegistry::class);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'data' => $registry->get($this->type)->toResource($this->payload ?? []),
        ];
    }
}
