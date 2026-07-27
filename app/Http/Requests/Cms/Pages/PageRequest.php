<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Pages;

use App\Enums\Cms\ContentStatus;
use App\Http\Requests\Cms\Concerns\HasSeoRules;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    use HasSeoRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branchId = app(BranchContext::class)->id();
        $pageId = $this->route('id') !== null ? (int) $this->route('id') : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages', 'slug')
                    ->where('branch_id', $branchId)
                    ->where('parent_id', $this->input('parent_id'))
                    ->ignore($pageId)
                    ->withoutTrashed(),
            ],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('pages', 'id')->where('branch_id', $branchId),
                Rule::notIn([$pageId]),
            ],
            'template' => ['required', 'string', Rule::in(array_keys(config('cms.templates')))],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'published_at' => ['nullable', 'date'],
            'position' => ['nullable', 'integer', 'min:0'],
            'featured_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('branch_id', $branchId)],
            ...$this->seoRules(),
            ...$this->blockRules(),
        ];
    }
}
