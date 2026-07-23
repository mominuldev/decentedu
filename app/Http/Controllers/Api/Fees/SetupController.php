<?php

namespace App\Http\Controllers\Api\Fees;

use App\Http\Controllers\Controller;
use App\Models\Fees\FeeHead;
use App\Models\Fees\FeeSubHead;
use App\Models\Fees\FeeWaiver;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Generic CRUD for the uniform fees setup resources (heads, sub-heads, waivers).
 * All queries are auto-scoped to the active branch by BelongsToBranch.
 */
class SetupController extends Controller
{
    public function index(Request $request, string $resource): JsonResponse
    {
        $config = $this->config($resource);
        $query = $config['model']::query();

        if (! empty($config['with'])) {
            $query->with($config['with']);
        }
        if ($request->filled('fee_head_id') && $resource === 'sub-heads') {
            $query->where('fee_head_id', $request->integer('fee_head_id'));
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('serial')->orderBy('id');
        $perPage = min((int) $request->query('per_page', 200), 500);
        $page = $query->paginate($perPage);

        return ApiResponse::success(
            collect($page->items())->map(fn (Model $m) => $this->transform($m, $config)),
            ucfirst(str_replace('-', ' ', $resource)).' retrieved.',
            ['pagination' => [
                'total' => $page->total(), 'per_page' => $page->perPage(),
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            ]],
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
            'heads' => [
                'model' => FeeHead::class,
                'table' => 'fee_heads',
                'fields' => ['name', 'name_bn', 'serial', 'status'],
                'extraRules' => [],
                'with' => [],
            ],
            'sub-heads' => [
                'model' => FeeSubHead::class,
                'table' => 'fee_sub_heads',
                'fields' => ['fee_head_id', 'name', 'name_bn', 'serial', 'status'],
                'extraRules' => ['fee_head_id' => ['required', 'integer', Rule::exists('fee_heads', 'id')]],
                'with' => ['feeHead'],
            ],
            'waivers' => [
                'model' => FeeWaiver::class,
                'table' => 'fee_waivers',
                'fields' => ['name', 'type', 'value', 'serial', 'status'],
                'extraRules' => [
                    'type' => ['required', Rule::in(['percentage', 'fixed'])],
                    'value' => ['required', 'numeric', 'min:0'],
                ],
                'with' => [],
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
        if ($m->relationLoaded('feeHead') && $m->feeHead) {
            $out['fee_head_name'] = $m->feeHead->name;
        }

        return $out;
    }
}
