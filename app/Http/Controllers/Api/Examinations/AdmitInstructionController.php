<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Models\Examinations\AdmitInstruction;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Free-text instructions printed on admit cards — one row per branch. */
class AdmitInstructionController extends Controller
{
    public function show(): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $row = AdmitInstruction::firstOrCreate(['branch_id' => $branchId]);

        return ApiResponse::success($row, 'OK');
    }

    public function update(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'instruction1' => ['nullable', 'string'],
            'instruction2' => ['nullable', 'string'],
            'instruction3' => ['nullable', 'string'],
            'instruction4' => ['nullable', 'string'],
        ]);

        $row = AdmitInstruction::updateOrCreate(['branch_id' => $branchId], $data);

        return ApiResponse::success($row, 'Updated.');
    }
}
