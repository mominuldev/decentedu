<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms\Menus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MenuTreeRequest extends FormRequest
{
    private const ALLOWED_LINKABLES = ['page', 'post', 'term'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['present', 'array'],
        ];
    }

    /**
     * Items nest to arbitrary depth, so the tree is validated recursively.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateLevel($validator, $this->input('items', []), 'items');
            },
        ];
    }

    private function validateLevel(Validator $validator, mixed $items, string $prefix): void
    {
        if (! is_array($items)) {
            $validator->errors()->add($prefix, 'Menu items must be an array.');

            return;
        }

        foreach (array_values($items) as $index => $item) {
            $key = "{$prefix}.{$index}";

            if (! is_array($item)) {
                $validator->errors()->add($key, 'Invalid menu item.');

                continue;
            }

            $label = $item['label'] ?? null;

            if (! is_string($label) || trim($label) === '' || mb_strlen($label) > 255) {
                $validator->errors()->add("{$key}.label", 'Every menu item needs a label (max 255 characters).');
            }

            $hasLinkable = ! empty($item['linkable_type']) && ! empty($item['linkable_id']);
            $hasUrl = ! empty($item['url']);

            if ($hasLinkable === $hasUrl) {
                $validator->errors()->add("{$key}.url", 'Each item must link to either content or a custom URL — not both, not neither.');
            }

            if ($hasLinkable && ! in_array($item['linkable_type'], self::ALLOWED_LINKABLES, true)) {
                $validator->errors()->add("{$key}.linkable_type", 'Invalid link target type.');
            }

            if (isset($item['target']) && ! in_array($item['target'], ['_self', '_blank'], true)) {
                $validator->errors()->add("{$key}.target", 'Invalid link target.');
            }

            $this->validateLevel($validator, $item['children'] ?? [], "{$key}.children");
        }
    }
}
