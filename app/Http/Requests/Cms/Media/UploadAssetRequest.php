<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Media;

use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadAssetRequest extends FormRequest
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
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => [
                'file',
                'max:'.config('cms.uploads.max_kb'),
                'mimes:'.implode(',', config('cms.uploads.accepted_mimes')),
            ],
            'folder_id' => [
                'nullable', 'integer',
                Rule::exists('media_folders', 'id')->where('branch_id', $branchId),
            ],
        ];
    }
}
