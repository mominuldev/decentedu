<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Actions\Cms\Pages\CreatePage;
use App\Actions\Cms\Pages\DeletePage;
use App\Actions\Cms\Pages\UpdatePage;
use App\Enums\Cms\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\Pages\PageRequest;
use App\Models\Cms\Page;
use App\Services\Cms\Blocks\BlockAdminPresenter;
use App\Services\Cms\Blocks\BlockRegistry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Page::query()->with(['updater:id,name']);

        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('path', 'like', "%{$search}%"));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $sortable = ['title', 'path', 'template', 'status', 'position', 'published_at', 'updated_at'];
        $sort = $request->string('sort')->value();
        if (in_array($sort, $sortable, true)) {
            $dir = $request->string('direction')->value() === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sort, $dir)->orderBy('id');
        } else {
            $query->orderBy('path');
        }

        $pages = $query->paginate(min($request->integer('per_page', 50), 200));

        return ApiResponse::success(
            collect($pages->items())->map(fn (Page $p): array => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'path' => $p->path,
                'parent_id' => $p->parent_id,
                'template' => $p->template,
                'status' => $p->status->value,
                'position' => $p->position,
                'published_at' => $p->published_at?->toIso8601String(),
                'updated_by' => $p->updater?->name,
                'deleted_at' => $p->deleted_at?->toIso8601String(),
            ])->all(),
            'Pages retrieved.',
            ['pagination' => [
                'total' => $pages->total(), 'per_page' => $pages->perPage(),
                'current_page' => $pages->currentPage(), 'last_page' => $pages->lastPage(),
            ]],
        );
    }

    public function meta(BlockRegistry $registry): JsonResponse
    {
        return ApiResponse::success([
            'templates' => collect(config('cms.templates'))
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()->all(),
            'statuses' => ContentStatus::options(),
            'block_types' => $registry->options(),
            'parents' => Page::query()->orderBy('path')->get(['id', 'title', 'path'])
                ->map(fn (Page $p): array => ['id' => $p->id, 'title' => $p->title, 'path' => $p->path])->all(),
        ], 'Page editor metadata.');
    }

    public function show(int $id, BlockAdminPresenter $presenter): JsonResponse
    {
        $page = Page::query()
            ->with(['seo.ogImageAsset', 'blocks', 'featuredAsset'])
            ->findOrFail($id);

        return ApiResponse::success($this->editorPayload($page, $presenter), 'Page retrieved.');
    }

    public function store(PageRequest $request, CreatePage $action, BlockAdminPresenter $presenter): JsonResponse
    {
        $page = $action->handle($request->validated(), $request->user());
        $page->load(['seo.ogImageAsset', 'blocks', 'featuredAsset']);

        return ApiResponse::success($this->editorPayload($page, $presenter), 'Page created.', status: 201);
    }

    public function update(PageRequest $request, int $id, UpdatePage $action, BlockAdminPresenter $presenter): JsonResponse
    {
        $page = Page::findOrFail($id);
        $page = $action->handle($page, $request->validated(), $request->user());
        $page->load(['seo.ogImageAsset', 'blocks', 'featuredAsset']);

        return ApiResponse::success($this->editorPayload($page, $presenter), 'Page updated.');
    }

    public function destroy(int $id, DeletePage $action): JsonResponse
    {
        $action->handle(Page::findOrFail($id));

        return ApiResponse::success(null, 'Page deleted.');
    }

    public function restore(int $id): JsonResponse
    {
        Page::onlyTrashed()->findOrFail($id)->restore();

        return ApiResponse::success(null, 'Page restored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function editorPayload(Page $page, BlockAdminPresenter $presenter): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'path' => $page->path,
            'parent_id' => $page->parent_id,
            'template' => $page->template,
            'excerpt' => $page->excerpt,
            'status' => $page->status->value,
            'published_at' => $page->published_at?->toIso8601String(),
            'position' => $page->position,
            'featured_asset_id' => $page->featured_asset_id,
            'featured_asset' => $page->featuredAsset?->toApiPayload(),
            'blocks' => $presenter->present($page->blocks),
            'seo' => $page->seo?->only([
                'meta_title', 'meta_description', 'canonical_url', 'robots',
                'og_title', 'og_description', 'og_image_asset_id', 'structured_data',
            ]),
            'seo_og_image' => $page->seo?->ogImageAsset?->toApiPayload(),
        ];
    }
}
