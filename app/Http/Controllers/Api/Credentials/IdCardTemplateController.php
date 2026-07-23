<?php

namespace App\Http\Controllers\Api\Credentials;

use App\Http\Controllers\Controller;
use App\Models\Credentials\IdCardTemplate;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Simple field-toggle config, no visual builder — `fields` just lists which of a fixed set
 * the frontend's fixed card layout component should render.
 */
class IdCardTemplateController extends Controller
{
    private const AVAILABLE_FIELDS = [
        'photo', 'name', 'roll', 'class', 'designation', 'blood_group',
        'address', 'guardian', 'mobile', 'validity', 'signature',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = IdCardTemplate::query();

        if ($holderType = $request->query('holder_type')) {
            $query->where('holder_type', $holderType);
        }

        return ApiResponse::success($query->orderBy('name')->get(), 'ID card templates retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate($this->rules($branchId, null));

        $template = IdCardTemplate::create($data + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]);

        return ApiResponse::success($template, 'ID card template created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $template = IdCardTemplate::findOrFail($id);
        $data = $request->validate($this->rules($branchId, $id));

        $template->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success($template, 'ID card template updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        IdCardTemplate::findOrFail($id)->delete();

        return ApiResponse::success(null, 'ID card template deleted.');
    }

    private function rules(int $branchId, ?int $ignoreId): array
    {
        $unique = Rule::unique('id_card_templates', 'name')
            ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at'))
            ->ignore($ignoreId);

        return [
            'name' => ['required', 'string', 'max:150', $unique],
            'holder_type' => ['required', Rule::in(['student', 'employee'])],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => [Rule::in(self::AVAILABLE_FIELDS)],
            'show_qr' => ['sometimes', 'boolean'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
