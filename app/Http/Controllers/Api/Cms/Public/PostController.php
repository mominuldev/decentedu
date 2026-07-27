<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cms\PostListResource;
use App\Http\Resources\Cms\PostResource;
use App\Models\Cms\Post;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Post::query()->published()->with(['featuredAsset.media', 'author', 'terms', 'tags']);

        if ($request->filled('category')) {
            $slug = $request->string('category')->value();
            $query->whereHas('terms', fn ($t) => $t->where('slug', $slug));
        }
        if ($request->filled('tag')) {
            $tag = $request->string('tag')->value();
            $query->whereHas('tags', fn ($t) => $t->where('name->en', $tag));
        }
        if ($request->filled('author')) {
            $query->where('author_id', $request->integer('author'));
        }
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('excerpt', 'like', "%{$search}%"));
        }

        $posts = $query->orderByDesc('published_at')->paginate(min($request->integer('per_page', 12), 50));

        return ApiResponse::success(
            PostListResource::collection(collect($posts->items())),
            'Posts retrieved.',
            ['pagination' => [
                'total' => $posts->total(), 'per_page' => $posts->perPage(),
                'current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage(),
            ]],
        );
    }

    public function show(string $slug): JsonResponse
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->with(['blocks', 'seo.ogImageAsset', 'featuredAsset.media', 'author', 'terms', 'tags'])
            ->firstOrFail();

        return ApiResponse::success((new PostResource($post))->resolve(), 'Post retrieved.');
    }
}
