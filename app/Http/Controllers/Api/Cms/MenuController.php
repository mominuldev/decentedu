<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\Menu;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Menu::with('items')->orderBy('name')->get(), 'Menus retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate($this->rules($branchId, null));

        $menu = Menu::create($data);

        return ApiResponse::success($menu, 'Menu created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $menu = Menu::findOrFail($id);
        $data = $request->validate($this->rules($branchId, $id));

        $menu->update($data);

        return ApiResponse::success($menu, 'Menu updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Menu::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Menu deleted.');
    }

    private function rules(int $branchId, ?int $ignoreId): array
    {
        $unique = Rule::unique('menus', 'name')->where(fn ($q) => $q->where('branch_id', $branchId))->ignore($ignoreId);

        return [
            'name' => ['required', 'string', 'max:150', $unique],
            'location' => ['required', Rule::in(['header', 'footer'])],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
