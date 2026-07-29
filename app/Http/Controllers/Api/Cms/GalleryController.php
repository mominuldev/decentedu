<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Enums\Cms\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\Galleries\GalleryRequest;
use App\Models\Cms\Gallery;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Gallery::query()->with(['coverAsset.media']);

        if ($request->boolean('trashed') || $request->input('status') === 'trashed') {
            $query->onlyTrashed();
        } elseif ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sortable = ['title', 'status', 'published_at', 'created_at'];
        $sort = (string) $request->input('sort');
        if (in_array($sort, $sortable, true)) {
            $dir = $request->input('direction') === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sort, $dir)->orderByDesc('id');
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        $galleries = $query->paginate(min($request->integer('per_page', 50), 200));

        $data = collect($galleries->items())->map(fn (Gallery $g): array => $g->toApiPayload())->all();

        return ApiResponse::success($data, 'Galleries retrieved.', [
            'pagination' => [
                'total' => $galleries->total(),
                'per_page' => $galleries->perPage(),
                'current_page' => $galleries->currentPage(),
                'last_page' => $galleries->lastPage(),
            ],
        ]);
    }

    public function store(GalleryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $statusVal = is_object($data['status'] ?? null) ? $data['status']->value : ($data['status'] ?? 'published');

        if ($statusVal === ContentStatus::Published->value && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $branchId = $request->user()?->branch_id ?? 1;

        $gallery = Gallery::create([
            ...$data,
            'branch_id' => $branchId,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $gallery->load('coverAsset.media');

        return ApiResponse::success($gallery->toApiPayload(), 'Gallery created successfully.', status: 201);
    }

    public function show(int|string $id): JsonResponse
    {
        $gallery = $this->findGallery($id)->load(['coverAsset.media']);

        return ApiResponse::success($gallery->toApiPayload(), 'Gallery retrieved.');
    }

    public function update(GalleryRequest $request, int|string $id): JsonResponse
    {
        $gallery = $this->findGallery($id);
        $data = $request->validated();
        $statusVal = is_object($data['status'] ?? null) ? $data['status']->value : ($data['status'] ?? 'published');

        if ($statusVal === ContentStatus::Published->value && empty($gallery->published_at) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $gallery->update([
            ...$data,
            'updated_by' => $request->user()?->id,
        ]);

        $gallery->refresh();
        $gallery->load('coverAsset.media');

        return ApiResponse::success($gallery->toApiPayload(), 'Gallery updated successfully.');
    }

    public function destroy(int|string $id): JsonResponse
    {
        $gallery = $this->findGallery($id);
        $gallery->delete();

        return ApiResponse::success(null, 'Gallery deleted successfully.');
    }

    public function restore(int|string $id): JsonResponse
    {
        $this->findGallery($id, onlyTrashed: true)->restore();

        return ApiResponse::success(null, 'Gallery restored successfully.');
    }

    public function forceDelete(int|string $id): JsonResponse
    {
        $this->findGallery($id, onlyTrashed: true)->forceDelete();

        return ApiResponse::success(null, 'Gallery permanently deleted.');
    }

    public function duplicate(int|string $id): JsonResponse
    {
        $original = $this->findGallery($id);

        $duplicate = Gallery::create([
            'branch_id' => $original->branch_id,
            'title' => $original->title.' (Copy)',
            'description' => $original->description,
            'cover_asset_id' => $original->cover_asset_id,
            'images' => $original->images,
            'status' => ContentStatus::Draft->value,
            'published_at' => null,
            'created_by' => request()->user()?->id,
            'updated_by' => request()->user()?->id,
        ]);

        $duplicate->load('coverAsset.media');

        return ApiResponse::success($duplicate->toApiPayload(), 'Gallery duplicated as draft.', status: 201);
    }

    private function findGallery(int|string $idOrSlug, bool $onlyTrashed = false): Gallery
    {
        $query = $onlyTrashed ? Gallery::onlyTrashed() : Gallery::query();

        return $query->where(fn ($q) => $q->where('id', $idOrSlug)->orWhere('slug', $idOrSlug))->firstOrFail();
    }
}
