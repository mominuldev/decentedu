<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Actions\Cms\Posts\CreatePost;
use App\Actions\Cms\Posts\UpdatePost;
use App\Enums\Cms\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\Posts\PostRequest;
use App\Models\Cms\Post;
use App\Models\Cms\Term;
use App\Models\User;
use App\Services\Cms\Blocks\BlockAdminPresenter;
use App\Services\Cms\Blocks\BlockRegistry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Tags\Tag;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Post::query()->with(['author:id,name', 'terms:id,name']);

        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }
        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->string('search')->value().'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }
        if ($request->filled('author')) {
            $query->where('author_id', $request->integer('author'));
        }

        $sortable = ['title', 'status', 'is_featured', 'published_at', 'created_at'];
        $sort = $request->string('sort')->value();
        if (in_array($sort, $sortable, true)) {
            $dir = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sort, $dir)->orderByDesc('id');
        } else {
            $query->orderByDesc('published_at')->orderByDesc('id');
        }

        $posts = $query->paginate(min($request->integer('per_page', 50), 200));

        return ApiResponse::success(
            collect($posts->items())->map(fn (Post $p): array => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'status' => $p->status->value,
                'is_featured' => $p->is_featured,
                'author' => $p->author?->name,
                'published_at' => $p->published_at?->toIso8601String(),
                'categories' => $p->terms->pluck('name')->all(),
                'deleted_at' => $p->deleted_at?->toIso8601String(),
            ])->all(),
            'Posts retrieved.',
            ['pagination' => [
                'total' => $posts->total(), 'per_page' => $posts->perPage(),
                'current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage(),
            ]],
        );
    }

    public function meta(BlockRegistry $registry): JsonResponse
    {
        return ApiResponse::success([
            'statuses' => ContentStatus::options(),
            'block_types' => $registry->options(),
            'authors' => User::query()->orderBy('name')->get(['id', 'name']),
            'terms' => Term::query()->with('taxonomy:id,name')->orderBy('name')
                ->whereHas('taxonomy', fn ($q) => $q->forObjectType('post'))
                ->get(['id', 'name', 'taxonomy_id'])
                ->map(fn (Term $t): array => ['id' => $t->id, 'name' => $t->name, 'taxonomy' => $t->taxonomy?->name])->all(),
            'tags' => Tag::query()->orderBy('name')->pluck('name')->all(),
        ], 'Post editor metadata.');
    }

    public function show(int $id, BlockAdminPresenter $presenter): JsonResponse
    {
        $post = Post::query()
            ->with(['seo.ogImageAsset', 'blocks', 'terms:id,name', 'featuredAsset', 'tags'])
            ->findOrFail($id);

        return ApiResponse::success($this->editorPayload($post, $presenter), 'Post retrieved.');
    }

    public function store(PostRequest $request, CreatePost $action, BlockAdminPresenter $presenter): JsonResponse
    {
        $post = $action->handle($request->validated(), $request->user());
        $post->load(['seo.ogImageAsset', 'blocks', 'terms:id,name', 'featuredAsset', 'tags']);

        return ApiResponse::success($this->editorPayload($post, $presenter), 'Post created.', status: 201);
    }

    public function update(PostRequest $request, int $id, UpdatePost $action, BlockAdminPresenter $presenter): JsonResponse
    {
        $post = Post::findOrFail($id);
        $post = $action->handle($post, $request->validated(), $request->user());
        $post->load(['seo.ogImageAsset', 'blocks', 'terms:id,name', 'featuredAsset', 'tags']);

        return ApiResponse::success($this->editorPayload($post, $presenter), 'Post updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Post::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Post deleted.');
    }

    public function restore(int $id): JsonResponse
    {
        Post::onlyTrashed()->findOrFail($id)->restore();

        return ApiResponse::success(null, 'Post restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function editorPayload(Post $post, BlockAdminPresenter $presenter): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'author_id' => $post->author_id,
            'status' => $post->status->value,
            'published_at' => $post->published_at?->toIso8601String(),
            'is_featured' => $post->is_featured,
            'reading_time' => $post->reading_time,
            'featured_asset_id' => $post->featured_asset_id,
            'featured_asset' => $post->featuredAsset?->toApiPayload(),
            'terms' => $post->terms->pluck('id')->all(),
            'tags' => $post->tags->pluck('name')->all(),
            'blocks' => $presenter->present($post->blocks),
            'seo' => $post->seo?->only([
                'meta_title', 'meta_description', 'canonical_url', 'robots',
                'og_title', 'og_description', 'og_image_asset_id', 'structured_data',
            ]),
            'seo_og_image' => $post->seo?->ogImageAsset?->toApiPayload(),
        ];
    }
}
