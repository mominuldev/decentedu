<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\Post;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    private const TYPES = [
        'page', 'news', 'notice', 'slider', 'teacher', 'staff', 'committee',
        'gallery', 'result', 'homepage_person', 'instruction',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Post::query();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where('title', 'like', "%{$search}%");
        }

        $perPage = min((int) $request->query('per_page', 50), 200);
        $page = $query->orderByDesc('id')->paginate($perPage);

        return ApiResponse::success($page->items(), 'Posts retrieved.', ['pagination' => [
            'total' => $page->total(), 'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
        ]]);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(Post::findOrFail($id), 'Post retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate($this->rules($branchId, null));

        $data['body'] = isset($data['body']) ? HtmlSanitizer::clean($data['body']) : null;
        $data['slug'] = $this->uniqueSlug($branchId, $data['type'], $data['slug'] ?? $data['title'], null);

        $post = Post::create($data + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]);

        return ApiResponse::success($post, 'Post created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $post = Post::findOrFail($id);
        $data = $request->validate($this->rules($branchId, $id));

        $data['body'] = isset($data['body']) ? HtmlSanitizer::clean($data['body']) : null;
        $data['slug'] = $this->uniqueSlug($branchId, $data['type'], $data['slug'] ?? $data['title'], $id);

        $post->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success($post, 'Post updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Post::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Post deleted.');
    }

    private function rules(int $branchId, ?int $ignoreId): array
    {
        return [
            'type' => ['required', Rule::in(self::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:500'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
        ];
    }

    private function uniqueSlug(int $branchId, string $type, string $source, ?int $ignoreId): string
    {
        $base = Str::slug($source) ?: 'post';
        $slug = $base;
        $n = 1;

        while (Post::withoutBranchScope()
            ->where('branch_id', $branchId)->where('type', $type)->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$base}-".++$n;
        }

        return $slug;
    }
}
