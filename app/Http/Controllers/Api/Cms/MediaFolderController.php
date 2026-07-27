<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\Media\MediaFolderRequest;
use App\Models\Cms\MediaFolder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MediaFolderController extends Controller
{
    public function index(): JsonResponse
    {
        $folders = MediaFolder::query()
            ->withCount('assets')
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'position']);

        return ApiResponse::success($folders, 'Folders retrieved.');
    }

    public function store(MediaFolderRequest $request): JsonResponse
    {
        $folder = MediaFolder::query()->create($request->validated());

        return ApiResponse::success($folder, 'Folder created.', status: 201);
    }

    public function update(MediaFolderRequest $request, int $id): JsonResponse
    {
        $folder = MediaFolder::findOrFail($id);
        $folder->update($request->validated());

        return ApiResponse::success($folder, 'Folder renamed.');
    }

    public function destroy(int $id): JsonResponse
    {
        $folder = MediaFolder::findOrFail($id);

        // Contents move to the parent folder rather than being destroyed.
        $folder->assets()->update(['media_folder_id' => $folder->parent_id]);
        $folder->children()->update(['parent_id' => $folder->parent_id]);
        $folder->delete();

        return ApiResponse::success(null, 'Folder deleted.');
    }
}
