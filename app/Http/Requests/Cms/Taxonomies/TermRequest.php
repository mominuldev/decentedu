<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Taxonomies;

use App\Models\Cms\Term;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TermRequest extends FormRequest
{
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
        $termId = $this->route('id') !== null ? (int) $this->route('id') : null;
        $existing = $termId !== null ? Term::query()->find($termId) : null;
        $taxonomyId = $existing?->taxonomy_id ?? $this->input('taxonomy_id');

        return [
            'taxonomy_id' => [
                $existing ? 'nullable' : 'required', 'integer',
                Rule::exists('taxonomies', 'id')->where('branch_id', $branchId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('terms', 'slug')->where('taxonomy_id', $taxonomyId)->ignore($termId),
            ],
            'parent_id' => [
                'nullable', 'integer', Rule::notIn([$termId]),
                Rule::exists('terms', 'id')->where('taxonomy_id', $taxonomyId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'position' => ['nullable', 'integer', 'min:0'],
            'featured_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('branch_id', $branchId)],
        ];
    }
}
