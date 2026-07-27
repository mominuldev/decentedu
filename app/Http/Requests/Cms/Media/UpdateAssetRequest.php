<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Media;

use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
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

        return [
            'name' => ['required', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
            'media_folder_id' => [
                'nullable', 'integer',
                Rule::exists('media_folders', 'id')->where('branch_id', $branchId),
            ],
        ];
    }
}
