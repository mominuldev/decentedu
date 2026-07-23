<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Models\Examinations\Exam;
use App\Models\Examinations\ShortCode;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Generic CRUD for the uniform examinations setup resources (exams, short-codes).
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
        $data = $request->validate($this->rules($config, $branchId, null));

        $model = $config['model']::create($data + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]);

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
        $data = $request->validate($this->rules($config, $branchId, $id));

        $model->update($data + ['updated_by' => auth()->id()]);

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
        $configs = [
            'exams' => [
                'model' => Exam::class,
                'table' => 'exams',
                'fields' => ['name', 'name_bn', 'type', 'serial', 'status'],
                'extraRules' => ['type' => ['required', Rule::in(['weekly', 'monthly', 'final', 'grand_final'])]],
            ],
            'short-codes' => [
                'model' => ShortCode::class,
                'table' => 'short_codes',
                'fields' => ['name', 'serial', 'status'],
                'extraRules' => [],
            ],
        ];

        $config = $configs[$resource] ?? null;
        abort_if($config === null, 404, 'Unknown resource.');

        return $config;
    }

    private function rules(array $config, int $branchId, ?int $ignoreId): array
    {
        $unique = Rule::unique($config['table'], 'name')
            ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at'))
            ->ignore($ignoreId);

        return array_merge([
            'name' => ['required', 'string', 'max:150', $unique],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'serial' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ], $config['extraRules']);
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
