<?php

declare(strict_types=1);

namespace App\Services\Cms\Blocks;

use App\Enums\Cms\BlockType;

interface BlockTypeContract
{
    public function type(): BlockType;

    /**
     * Validation rules applied to the block payload. Keys are relative to
     * the payload root (e.g. "heading" => "required|string").
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Transform a stored payload into its public API representation.
     * Resolve asset IDs to URLs, post lists to summaries, etc.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function toResource(array $payload): array;

    /**
     * Whether this type may hold other blocks nested in payload.blocks.
     */
    public function supportsChildren(): bool;
}
