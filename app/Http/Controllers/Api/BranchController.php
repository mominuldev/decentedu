<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AuthPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    /** Switch the active branch context (must be one the user belongs to). */
    public function switch(Request $request): JsonResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer']]);

        $belongs = $request->user()->branches()
            ->where('branches.id', $data['branch_id'])
            ->where('branches.status', true)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'branch_id' => ['You do not have access to this branch.'],
            ]);
        }

        $request->session()->put('active_branch_id', $data['branch_id']);

        return ApiResponse::success(AuthPayload::for($request->user(), $request), 'Branch switched.');
    }
}
