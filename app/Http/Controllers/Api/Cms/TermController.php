<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\Taxonomies\TermRequest;
use App\Models\Cms\Term;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TermController extends Controller
{
    public function store(TermRequest $request): JsonResponse
    {
        $term = Term::query()->create($request->validated());

        return ApiResponse::success($term, 'Term created.', status: 201);
    }

    public function update(TermRequest $request, int $id): JsonResponse
    {
        $term = $this->scopedTerm($id);
        $term->update(collect($request->validated())->except('taxonomy_id')->all());

        return ApiResponse::success($term, 'Term updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->scopedTerm($id)->delete();

        return ApiResponse::success(null, 'Term deleted.');
    }

    /**
     * Resolve a term only when its taxonomy belongs to the active branch —
     * terms aren't branch-scoped directly, they inherit it from the taxonomy.
     */
    private function scopedTerm(int $id): Term
    {
        return Term::query()->whereHas('taxonomy')->findOrFail($id);
    }
}
