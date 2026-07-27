<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Enums\Cms\TaxonomyObjectType;
use App\Http\Controllers\Controller;
use App\Models\Cms\Taxonomy;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxonomyController extends Controller
{
    public function index(): JsonResponse
    {
        $taxonomies = Taxonomy::query()->withCount('terms')->orderBy('name')->get();

        return ApiResponse::success($taxonomies, 'Taxonomies retrieved.', meta: [
            'object_types' => TaxonomyObjectType::options(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255',
                Rule::unique('taxonomies', 'name')->where('branch_id', $branchId)],
            'hierarchical' => ['boolean'],
            'object_types' => ['required', 'array', 'min:1'],
            'object_types.*' => [Rule::enum(TaxonomyObjectType::class)],
        ]);

        $taxonomy = Taxonomy::query()->create($data);

        return ApiResponse::success($taxonomy, 'Taxonomy created.', status: 201);
    }

    public function show(int $id): JsonResponse
    {
        $taxonomy = Taxonomy::findOrFail($id);

        $terms = $taxonomy->terms()
            ->with('parent:id,name')
            ->withCount('posts')
            ->orderBy('position')->orderBy('name')
            ->get();

        return ApiResponse::success([
            'taxonomy' => $taxonomy,
            'terms' => $terms->map(fn ($t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'parent_id' => $t->parent_id,
                'parent' => $t->parent?->name,
                'description' => $t->description,
                'position' => $t->position,
                'featured_asset_id' => $t->featured_asset_id,
                'posts_count' => $t->posts_count,
            ])->all(),
        ], 'Terms retrieved.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $taxonomy = Taxonomy::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255',
                Rule::unique('taxonomies', 'name')->where('branch_id', $branchId)->ignore($taxonomy->id)],
            'hierarchical' => ['boolean'],
            'object_types' => ['required', 'array', 'min:1'],
            'object_types.*' => [Rule::enum(TaxonomyObjectType::class)],
        ]);

        $taxonomy->update($data);

        return ApiResponse::success($taxonomy, 'Taxonomy updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Taxonomy::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Taxonomy deleted.');
    }
}
