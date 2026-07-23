<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\MenuItem;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['menu_id' => ['required', 'integer', Rule::exists('menus', 'id')]]);

        $items = MenuItem::where('menu_id', $data['menu_id'])->whereNull('parent_id')->with('children')->orderBy('serial')->get();

        return ApiResponse::success($items, 'Menu items retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $item = MenuItem::create($data);

        return ApiResponse::success($item, 'Menu item created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = MenuItem::findOrFail($id);
        $data = $request->validate($this->rules());

        $item->update($data);

        return ApiResponse::success($item, 'Menu item updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        MenuItem::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Menu item deleted.');
    }

    private function rules(): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        return [
            'menu_id' => ['required', 'integer', Rule::exists('menus', 'id')->where('branch_id', $branchId)],
            'label' => ['required', 'string', 'max:150'],
            'url' => ['nullable', 'string', 'max:255'],
            'post_id' => ['nullable', 'integer', Rule::exists('posts', 'id')->where('branch_id', $branchId)],
            'parent_id' => ['nullable', 'integer', Rule::exists('menu_items', 'id')],
            'serial' => ['sometimes', 'integer', 'min:0'],
            'target' => ['sometimes', Rule::in(['_self', '_blank'])],
        ];
    }
}
