<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Menus;

use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
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
        $menuId = $this->route('id') !== null ? (int) $this->route('id') : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('menus', 'key')->where('branch_id', $branchId)->ignore($menuId),
            ],
            'is_active' => ['boolean'],
        ];
    }
}
