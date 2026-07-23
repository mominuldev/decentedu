<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Support\AcademicResources;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Generic CRUD for the uniform branch-scoped setup resources
 * (academic-years, classes, shifts, sections, groups, categories, subjects).
 * All queries are auto-scoped to the active branch by BelongsToBranch.
 */
class SetupController extends Controller
{
    public function index(Request $request, string $resource): JsonResponse
    {
        $config = $this->config($resource);
        $query = $config['model']::query();

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('name_bn', 'like', "%{$search}%"));
        }

        $sort = $request->query('sort', 'serial');
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        if (! in_array($col, ['name', 'serial', 'created_at', 'status'], true)) {
            $col = 'serial';
        }
        $query->orderBy($col, $dir)->orderBy('id', 'asc');

        $perPage = min((int) $request->query('per_page', 25), 200);
        $page = $query->paginate($perPage);

        return ApiResponse::success(
            collect($page->items())->map(fn (Model $m) => $this->transform($m, $config)),
            ucfirst(str_replace('-', ' ', $resource)).' retrieved.',
            $this->pageMeta($page),
        );
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $config = $this->config($resource);
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate(AcademicResources::rules($config, $branchId, null));

        $model = $config['model']::create($data);
        $this->applyExclusive($config, $model, $data);

        return ApiResponse::success($this->transform($model, $config), 'Created.', status: 201);
    }

    public function show(string $resource, int $id): JsonResponse
    {
        $config = $this->config($resource);
        $model = $config['model']::findOrFail($id);

        return ApiResponse::success($this->transform($model, $config), 'OK');
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        $config = $this->config($resource);
        $branchId = app(BranchContext::class)->idOrFail();
        $model = $config['model']::findOrFail($id);
        $data = $request->validate(AcademicResources::rules($config, $branchId, $id));

        $model->update($data);
        $this->applyExclusive($config, $model, $data);

        return ApiResponse::success($this->transform($model, $config), 'Updated.');
    }

    public function destroy(string $resource, int $id): JsonResponse
    {
        $config = $this->config($resource);
        $config['model']::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function config(string $resource): array
    {
        $config = AcademicResources::find($resource);
        abort_if($config === null, 404, 'Unknown resource.');

        return $config;
    }

    /** Keep a single row flagged (e.g. only one current academic year). */
    private function applyExclusive(array $config, Model $model, array $data): void
    {
        if ($config['exclusive'] && ! empty($data[$config['exclusive']])) {
            $config['model']::query()
                ->whereKeyNot($model->getKey())
                ->update([$config['exclusive'] => false]);
        }
    }

    private function transform(Model $m, array $config): array
    {
        $out = ['id' => $m->id];
        foreach ($config['fields'] as $f) {
            $out[$f] = $m->{$f};
        }
        $out['created_at'] = $m->created_at?->toISOString();

        return $out;
    }

    private function pageMeta($page): array
    {
        return ['pagination' => [
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]];
    }
}
