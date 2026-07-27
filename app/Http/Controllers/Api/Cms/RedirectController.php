<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\Redirects\RedirectRequest;
use App\Models\Cms\Redirect;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedirectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Redirect::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(fn ($q) => $q->where('from_path', 'like', "%{$search}%")->orWhere('to_path', 'like', "%{$search}%"));
        }

        $redirects = $query->latest()->paginate(min($request->integer('per_page', 50), 200));

        return ApiResponse::success($redirects->items(), 'Redirects retrieved.', ['pagination' => [
            'total' => $redirects->total(), 'per_page' => $redirects->perPage(),
            'current_page' => $redirects->currentPage(), 'last_page' => $redirects->lastPage(),
        ]]);
    }

    public function store(RedirectRequest $request): JsonResponse
    {
        $redirect = Redirect::query()->create($request->validated());

        return ApiResponse::success($redirect, 'Redirect created.', status: 201);
    }

    public function update(RedirectRequest $request, int $id): JsonResponse
    {
        $redirect = Redirect::findOrFail($id);
        $redirect->update($request->validated());

        return ApiResponse::success($redirect, 'Redirect updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Redirect::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Redirect deleted.');
    }
}
