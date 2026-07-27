<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Redirects;

use App\Models\Cms\Redirect;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RedirectRequest extends FormRequest
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
        $redirectId = $this->route('id') !== null ? (int) $this->route('id') : null;

        return [
            'from_path' => [
                'required', 'string', 'max:255',
                Rule::unique('redirects', 'from_path')->where('branch_id', $branchId)->ignore($redirectId),
            ],
            'to_path' => ['required', 'string', 'max:255', 'different:from_path'],
            'status_code' => ['required', 'integer', Rule::in([301, 302])],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from_path' => Redirect::normalizePath((string) $this->input('from_path', '')),
            'to_path' => Redirect::normalizePath((string) $this->input('to_path', '')),
        ]);
    }
}
