<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Concerns;

use App\Enums\Cms\RobotsDirective;
use App\Support\BranchContext;
use Illuminate\Validation\Rule;

trait HasSeoRules
{
    /**
     * @return array<string, mixed>
     */
    protected function seoRules(): array
    {
        $branchId = app(BranchContext::class)->id();

        return [
            'seo' => ['nullable', 'array'],
            'seo.meta_title' => ['nullable', 'string', 'max:255'],
            'seo.meta_description' => ['nullable', 'string', 'max:500'],
            'seo.canonical_url' => ['nullable', 'url', 'max:255'],
            'seo.robots' => ['nullable', Rule::enum(RobotsDirective::class)],
            'seo.og_title' => ['nullable', 'string', 'max:255'],
            'seo.og_description' => ['nullable', 'string', 'max:500'],
            'seo.og_image_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('branch_id', $branchId)],
            'seo.structured_data' => ['nullable', 'array'],
        ];
    }

    /**
     * Block payloads are validated per type inside the SyncBlocks action;
     * these rules only cover the envelope.
     *
     * @return array<string, mixed>
     */
    protected function blockRules(): array
    {
        return [
            'blocks' => ['nullable', 'array'],
            'blocks.*.id' => ['nullable', 'integer'],
            'blocks.*.type' => ['required', 'string'],
            'blocks.*.payload' => ['present', 'array'],
            'blocks.*.is_visible' => ['boolean'],
        ];
    }
}
