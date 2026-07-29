<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Actions\Cms\Media\UploadAsset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\Media\UpdateAssetRequest;
use App\Http\Requests\Cms\Media\UploadAssetRequest;
use App\Models\Cms\Asset;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * One shared media library backs both CMS content images and non-CMS uploads (Student/Employee
 * photos, Branch logos) — `category` picks the pool ('cms' by default) and, for the private
 * categories, the storage disk. Only 'cms' actions require the `cms.manage` permission; photo/logo
 * categories are open to any authenticated branch user, matching the old dedicated uploads endpoint
 * they replace.
 */
class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $category = $this->category($request);
        $this->authorizeCategory($request, $category);

        $query = Asset::query()->where('category', $category)->with(['media', 'folder:id,name', 'uploader:id,name']);

        if ($request->filled('folder')) {
            $query->where('media_folder_id', $request->integer('folder'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search')->value().'%');
        }

        $this->applyTypeFilter($query, $request->string('type')->value());

        $assets = $query->latest()->paginate(min($request->integer('per_page', 20), 100));

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

    public function stats(Request $request): JsonResponse
    {
        $category = $this->category($request);
        $this->authorizeCategory($request, $category);

        $bytes = (int) Media::query()
            ->whereHasMorph('model', [Asset::class], fn ($q) => $q->where('category', $category))
            ->sum('size');

        return ApiResponse::success([
            'files' => Asset::query()->where('category', $category)->count(),
            'size_bytes' => $bytes,
        ], 'Stats retrieved.');
    }

    /**
     * JSON endpoint backing the MediaPicker / FileUpload "Browse Library" dialogs.
     */
    public function picker(Request $request): JsonResponse
    {
        $category = $this->category($request);
        $this->authorizeCategory($request, $category);

        $query = Asset::query()->where('category', $category)->with('media');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search')->value().'%');
        }

        $this->applyTypeFilter($query, $request->string('type')->value());

        $assets = $query->latest()->paginate(24);

        $data = collect($assets->items())->map(fn (Asset $asset): array => $asset->toApiPayload())->all();

        return ApiResponse::success($data, 'Assets retrieved.', ['pagination' => [
            'total' => $assets->total(), 'per_page' => $assets->perPage(),
            'current_page' => $assets->currentPage(), 'last_page' => $assets->lastPage(),
        ]]);
    }

    public function store(UploadAssetRequest $request, UploadAsset $action): JsonResponse
    {
        $category = $request->validated('category') ?? 'cms';
        $this->authorizeCategory($request, $category);

        $folderId = $request->filled('folder_id') ? (int) $request->validated('folder_id') : null;

        $assets = collect($request->validated('files'))
            ->map(fn (UploadedFile $file): Asset => $action->handle($file, $folderId, $request->user(), $category))
            ->map(fn (Asset $asset): array => $asset->toApiPayload())
            ->all();

        return ApiResponse::success($assets, count($assets).' file(s) uploaded.', status: 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $asset = Asset::query()->with('media')->findOrFail($id);
        $this->authorizeCategory($request, $asset->category);

        return ApiResponse::success($asset->toApiPayload(), 'Asset retrieved.');
    }

    public function update(UpdateAssetRequest $request, int $id): JsonResponse
    {
        $asset = Asset::query()->with('media')->findOrFail($id);
        $this->authorizeCategory($request, $asset->category);

        $asset->update($request->validated());

        return ApiResponse::success($asset->toApiPayload(), 'Asset updated.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);
        $this->authorizeCategory($request, $asset->category);

        $asset->delete();

        return ApiResponse::success(null, 'Asset deleted.');
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $assets = Asset::query()->findMany($validated['ids']);
        $assets->each(fn (Asset $asset) => $this->authorizeCategory($request, $asset->category));
        $assets->each->delete();

        return ApiResponse::success(null, $assets->count().' asset(s) deleted.');
    }

    /**
     * Streams a private-category asset's original file (or a named conversion) — the only way
     * to reach photo/logo assets, since they carry no public URL.
     */
    public function serve(Request $request, int $id): BinaryFileResponse
    {
        $asset = Asset::query()->findOrFail($id);
        abort_unless($asset->isPrivate(), 404);

        $media = $asset->file();
        abort_if(! $media, 404);

        $conversion = $request->string('conversion')->value();
        $path = ($conversion !== '' && $media->hasGeneratedConversion($conversion))
            ? $media->getPath($conversion)
            : $media->getPath();

        return response()->file($path);
    }

    /**
     * Public endpoint for serving media files (teacher photos, logos, etc.) on the public site.
     * No authentication required. Only serves private-category assets.
     */
    public function servePublic(Request $request, int $id): BinaryFileResponse
    {
        $asset = Asset::query()->findOrFail($id);
        abort_unless($asset->isPrivate(), 404);

        $media = $asset->file();
        abort_if(! $media, 404);

        $conversion = $request->string('conversion')->value();
        $path = ($conversion !== '' && $media->hasGeneratedConversion($conversion))
            ? $media->getPath($conversion)
            : $media->getPath();

        return response()->file($path);
    }

    private function category(Request $request): string
    {
        return $request->string('category')->value() ?: 'cms';
    }

    private function authorizeCategory(Request $request, string $category): void
    {
        if ($category === 'cms') {
            abort_unless($request->user()?->can('cms.manage'), 403, 'Insufficient permissions.');
        }
    }

    /**
     * @param  Builder<Asset>  $query
     */
    private function applyTypeFilter(Builder $query, string $type): void
    {
        if ($type === '' || $type === 'all') {
            return;
        }

        $query->whereHas('media', function ($media) use ($type): void {
            match ($type) {
                'image' => $media->where('mime_type', 'like', 'image/%'),
                'video' => $media->where('mime_type', 'like', 'video/%'),
                'audio' => $media->where('mime_type', 'like', 'audio/%'),
                'document' => $media
                    ->where('mime_type', 'not like', 'image/%')
                    ->where('mime_type', 'not like', 'video/%')
                    ->where('mime_type', 'not like', 'audio/%'),
                default => null,
            };
        });
    }
}
