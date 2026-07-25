<?php

namespace App\Http\Controllers\Api\Admissions;

use App\Http\Controllers\Controller;
use App\Models\Admissions\AdmissionYear;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmissionYearController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AdmissionYear::query()
            ->withCount('applications')
            ->with('academicYear:id,name')
            ->orderBy('serial')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return ApiResponse::success($query->get(), 'Admission years retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'title' => "required|string|max:255|unique:admission_years,title,NULL,id,branch_id,{$branchId},deleted_at,NULL",
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:open,closed',
            'serial' => 'nullable|integer|min:0',
        ]);

        $year = AdmissionYear::create($data + [
            'branch_id' => $branchId,
            'status' => $data['status'] ?? 'open',
            'created_by' => auth()->id(),
        ]);

        return ApiResponse::success($year, 'Admission year created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $year = AdmissionYear::findOrFail($id);

        $data = $request->validate([
            'title' => "sometimes|required|string|max:255|unique:admission_years,title,{$id},id,branch_id,{$branchId},deleted_at,NULL",
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:open,closed',
            'serial' => 'nullable|integer|min:0',
        ]);

        $year->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success($year->fresh(), 'Admission year updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $year = AdmissionYear::withCount('applications')->findOrFail($id);

        if ($year->applications_count > 0) {
            return ApiResponse::error('Cannot delete an admission year that has applications.', 'HAS_APPLICATIONS', 409);
        }

        $year->delete();

        return ApiResponse::success(null, 'Admission year deleted.');
    }
}
