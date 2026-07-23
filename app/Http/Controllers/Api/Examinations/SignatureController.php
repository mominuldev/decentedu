<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Models\Examinations\Signature;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Named signatures (principal, controller…) placed on printed marksheets/admit cards. */
class SignatureController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Signature::orderBy('serial')->get();

        return ApiResponse::success($rows, 'Signatures retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $signature = Signature::create($this->validated($request));

        return ApiResponse::success($signature, 'Created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $signature = Signature::findOrFail($id);
        $signature->update($this->validated($request));

        return ApiResponse::success($signature, 'Updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Signature::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'position' => ['required', Rule::in(['left', 'middle', 'right'])],
            'person_name' => ['required', 'string', 'max:150'],
            'designation' => ['required', 'string', 'max:150'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'serial' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'boolean'],
        ]);
    }
}
