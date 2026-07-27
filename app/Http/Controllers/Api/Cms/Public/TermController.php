<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cms\TermResource;
use App\Models\Cms\Taxonomy;
use App\Models\Cms\Term;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TermController extends Controller
{
    public function index(string $taxonomy): JsonResponse
    {
        $taxonomyModel = Taxonomy::query()->where('slug', $taxonomy)->firstOrFail();

        $terms = $taxonomyModel->terms()
            ->whereNull('parent_id')
            ->withCount('posts')
            ->with(['children' => fn ($q) => $q->withCount('posts'), 'featuredAsset.media'])
            ->orderBy('position')
            ->get();

        return ApiResponse::success(TermResource::collection($terms)->resolve(), 'Terms retrieved.');
    }

    public function show(string $taxonomy, string $slug): JsonResponse
    {
        $term = Term::query()
            ->whereHas('taxonomy', fn ($q) => $q->where('slug', $taxonomy))
            ->where('slug', $slug)
            ->withCount('posts')
            ->with(['children' => fn ($q) => $q->withCount('posts'), 'featuredAsset.media'])
            ->firstOrFail();

        return ApiResponse::success((new TermResource($term))->resolve(), 'Term retrieved.');
    }
}
