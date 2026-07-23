<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Messaging\SmsTemplate;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = SmsTemplate::orderBy('name')->get();

        return ApiResponse::success($templates, 'Templates retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate($this->rules($branchId, null));

        $template = SmsTemplate::create($data + ['created_by' => auth()->id(), 'updated_by' => auth()->id()]);

        return ApiResponse::success($template, 'Template created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $template = SmsTemplate::findOrFail($id);
        $data = $request->validate($this->rules($branchId, $id));

        $template->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success($template, 'Template updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        SmsTemplate::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Template deleted.');
    }

    private function rules(int $branchId, ?int $ignoreId): array
    {
        $unique = Rule::unique('sms_templates', 'name')
            ->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at'))
            ->ignore($ignoreId);

        return [
            'name' => ['required', 'string', 'max:150', $unique],
            'type' => ['required', Rule::in(['attendance', 'result', 'fee', 'general', 'custom'])],
            'message' => ['required', 'string', 'max:1000'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
