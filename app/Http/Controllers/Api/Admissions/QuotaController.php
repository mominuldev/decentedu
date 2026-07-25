<?php

namespace App\Http\Controllers\Api\Admissions;

use App\Http\Controllers\Controller;
use App\Models\Admissions\Quota;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotaController extends Controller
{
    public function index(): JsonResponse
    {
        $quotas = Quota::query()
            ->withCount('applications')
            ->orderBy('serial')
            ->orderBy('name')
            ->get();

        return ApiResponse::success($quotas, 'Quotas retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'name' => "required|string|max:255|unique:quotas,name,NULL,id,branch_id,{$branchId}",
            'description' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:0',
            'status' => 'nullable|boolean',
            'serial' => 'nullable|integer|min:0',
        ]);

        $quota = Quota::create($data + [
            'branch_id' => $branchId,
            'status' => $data['status'] ?? true,
            'created_by' => auth()->id(),
        ]);

        return ApiResponse::success($quota, 'Quota created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $quota = Quota::findOrFail($id);

        $data = $request->validate([
            'name' => "sometimes|required|string|max:255|unique:quotas,name,{$id},id,branch_id,{$branchId}",
            'description' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:0',
            'status' => 'nullable|boolean',
            'serial' => 'nullable|integer|min:0',
        ]);

        $quota->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success($quota->fresh(), 'Quota updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $quota = Quota::withCount('applications')->findOrFail($id);

        if ($quota->applications_count > 0) {
            return ApiResponse::error('Cannot delete a quota that is in use by applications.', 'QUOTA_IN_USE', 409);
        }

        $quota->delete();

        return ApiResponse::success(null, 'Quota deleted.');
    }
}
