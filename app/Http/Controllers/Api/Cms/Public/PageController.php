<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cms\PageListResource;
use App\Http\Resources\Cms\PageResource;
use App\Models\Cms\Page;
use App\Models\Cms\Redirect;
use App\Services\Cms\Pages\PagePathResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Page::query()->published();

        if ($request->filled('template')) {
            $query->where('template', $request->string('template')->value());
        }
        if ($request->filled('parent')) {
            $query->where('parent_id', $request->integer('parent'));
        }

        $pages = $query->orderBy('position')->paginate(min($request->integer('per_page', 25), 50));

        return ApiResponse::success(
            PageListResource::collection(collect($pages->items())),
            'Pages retrieved.',
            ['pagination' => [
                'total' => $pages->total(), 'per_page' => $pages->perPage(),
                'current_page' => $pages->currentPage(), 'last_page' => $pages->lastPage(),
            ]],
        );
    }

    public function show(string $path, PagePathResolver $resolver): JsonResponse
    {
        $page = $resolver->resolve($path);

        if ($page !== null) {
            $page->load([
                'blocks',
                'children' => fn ($query) => $query->published(),
            ]);

            return ApiResponse::success((new PageResource($page))->resolve(), 'Page retrieved.');
        }

        return $this->redirectOrNotFound($path);
    }

    private function redirectOrNotFound(string $path): JsonResponse
    {
        $redirect = Redirect::query()
            ->where('from_path', Redirect::normalizePath($path))
            ->where('is_active', true)
            ->first();

        if ($redirect === null) {
            abort(404, 'Page not found.');
        }

        $redirect->recordHit();

        return ApiResponse::success([
            'redirect_to' => '/'.$redirect->to_path,
            'status_code' => $redirect->status_code,
        ], 'Redirect.', status: $redirect->status_code);
    }
}
