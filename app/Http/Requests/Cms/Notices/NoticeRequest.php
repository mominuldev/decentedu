<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Notices;

use App\Enums\Cms\ContentStatus;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NoticeRequest extends FormRequest
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
        $noticeId = $this->route('id') !== null ? (int) $this->route('id') : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('notices', 'slug')->where('branch_id', $branchId)->ignore($noticeId)->withoutTrashed(),
            ],
            'body' => ['nullable', 'string'],
            'notice_date' => ['required', 'date'],
            'is_important' => ['boolean'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'published_at' => ['nullable', 'date'],
            'attachment_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('branch_id', $branchId)],
            'terms' => ['nullable', 'array'],
            'terms.*' => ['integer', Rule::exists('terms', 'id')],
        ];
    }
}
