<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Actions\Cms\Media\UploadAsset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\Media\UpdateAssetRequest;
use App\Http\Requests\Cms\Media\UploadAssetRequest;
use App\Models\Cms\Asset;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $folderId = $request->filled('folder') ? $request->integer('folder') : null;

        $query = Asset::query()->with(['media', 'folder:id,name', 'uploader:id,name']);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search')->value().'%');
        } else {
            $query->where('media_folder_id', $folderId);
        }

        $assets = $query->latest()->paginate(min($request->integer('per_page', 40), 100));

        $data = collect($assets->items())->map(fn (Asset $asset): array => [
            ...$asset->toApiPayload(),
            'folder' => $asset->folder?->only(['id', 'name']),
            'uploader' => $asset->uploader?->name,
            'created_at' => $asset->created_at?->toDateTimeString(),
        ])->all();

        return ApiResponse::success($data, 'Assets retrieved.', ['pagination' => [
            'total' => $assets->total(), 'per_page' => $assets->perPage(),
            'current_page' => $assets->currentPage(), 'last_page' => $assets->lastPage(),
        ]]);
    }

    /**
     * JSON endpoint backing the MediaPicker dialog.
     */
    public function picker(Request $request): JsonResponse
    {
        $query = Asset::query()->with('media');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search')->value().'%');
        }

        if ($request->string('type')->value() === 'image') {
            $query->whereHas('media', fn ($media) => $media->where('mime_type', 'like', 'image/%'));
        }

        $assets = $query->latest()->paginate(24);

        $data = collect($assets->items())->map(fn (Asset $asset): array => $asset->toApiPayload())->all();

        return ApiResponse::success($data, 'Assets retrieved.', ['pagination' => [
            'total' => $assets->total(), 'per_page' => $assets->perPage(),
            'current_page' => $assets->currentPage(), 'last_page' => $assets->lastPage(),
        ]]);
    }

    public function store(UploadAssetRequest $request, UploadAsset $action): JsonResponse
    {
        $folderId = $request->filled('folder_id') ? (int) $request->validated('folder_id') : null;

        $assets = collect($request->validated('files'))
            ->map(fn (UploadedFile $file): Asset => $action->handle($file, $folderId, $request->user()))
            ->map(fn (Asset $asset): array => $asset->toApiPayload())
            ->all();

        return ApiResponse::success($assets, count($assets).' file(s) uploaded.', status: 201);
    }

    public function update(UpdateAssetRequest $request, int $id): JsonResponse
    {
        $asset = Asset::query()->with('media')->findOrFail($id);
        $asset->update($request->validated());

        return ApiResponse::success($asset->toApiPayload(), 'Asset updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Asset::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Asset deleted.');
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $assets = Asset::query()->findMany($validated['ids']);
        $assets->each->delete();

        return ApiResponse::success(null, $assets->count().' asset(s) deleted.');
    }
}
