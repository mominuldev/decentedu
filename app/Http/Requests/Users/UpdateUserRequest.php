<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'boolean'],
            'branch_ids' => ['sometimes', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'default_branch_id' => ['nullable', 'integer', Rule::in($this->input('branch_ids', []))],
            'role' => ['sometimes', 'required', 'string', Rule::exists('roles', 'name')],
        ];
    }
}
