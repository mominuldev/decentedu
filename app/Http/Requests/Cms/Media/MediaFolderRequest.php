<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Media;

use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaFolderRequest extends FormRequest
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
        $folderId = $this->route('id') !== null ? (int) $this->route('id') : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('media_folders', 'id')->where('branch_id', $branchId),
                Rule::notIn([$folderId]),
            ],
        ];
    }
}
