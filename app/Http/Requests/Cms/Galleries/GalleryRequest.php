<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Galleries;

use App\Enums\Cms\ContentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cover_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')],
            'images' => ['nullable', 'array'],
            'images.*' => ['integer', Rule::exists('assets', 'id')],
            'status' => ['required', 'string', Rule::enum(ContentStatus::class)],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
