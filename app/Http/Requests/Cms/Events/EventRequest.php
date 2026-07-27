<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Events;

use App\Enums\Cms\ContentStatus;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
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
        $eventId = $this->route('id') !== null ? (int) $this->route('id') : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('events', 'slug')->where('branch_id', $branchId)->ignore($eventId)->withoutTrashed(),
            ],
            'body' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'featured_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('branch_id', $branchId)],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'published_at' => ['nullable', 'date'],
            'terms' => ['nullable', 'array'],
            'terms.*' => ['integer', Rule::exists('terms', 'id')],
        ];
    }
}
