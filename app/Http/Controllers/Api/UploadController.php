<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generic upload endpoint (doc 08: type/size/magic-byte validation, private disk, randomized
 * names). File::image()/File::types() validate real content, not just the extension. Every
 * *_path column across the app (photos, logos, CMS images) stores the relative path this
 * returns and resolves it back through the download route below — nothing is served directly
 * from a public disk.
 */
class UploadController extends Controller
{
    private const CATEGORIES = ['photo', 'logo', 'image'];

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'file' => ['required', File::image()->max(2 * 1024)],
        ]);

        $file = $request->file('file');
        $filename = Str::uuid().'.'.$file->extension();
        $path = $file->storeAs("uploads/{$branchId}/{$data['category']}", $filename, 'local');

        return ApiResponse::success(['path' => $path], 'File uploaded.', status: 201);
    }

    public function show(Request $request, string $path): StreamedResponse|Response
    {
        $branchId = app(BranchContext::class)->idOrFail();

        // Ownership check: the path's leading segment must be the caller's active branch.
        abort_unless(str_starts_with($path, "uploads/{$branchId}/"), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
