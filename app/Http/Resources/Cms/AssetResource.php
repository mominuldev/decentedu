<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Asset
 */
class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->toApiPayload();
    }
}
