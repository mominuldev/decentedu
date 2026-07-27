<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Posts;

use App\Enums\Cms\ContentStatus;
use App\Http\Requests\Cms\Concerns\HasSeoRules;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
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
        $postId = $this->route('id') !== null ? (int) $this->route('id') : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('posts', 'slug')->where('branch_id', $branchId)->ignore($postId)->withoutTrashed(),
            ],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string'],
            'author_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'featured_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('branch_id', $branchId)],
            'terms' => ['nullable', 'array'],
            'terms.*' => ['integer', Rule::exists('terms', 'id')],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            ...$this->seoRules(),
            ...$this->blockRules(),
        ];
    }
}
