<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'boolean'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'default_branch_id' => ['nullable', 'integer', Rule::in($this->input('branch_ids', []))],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ];
    }
}
