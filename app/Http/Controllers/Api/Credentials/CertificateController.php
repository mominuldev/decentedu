<?php

namespace App\Http\Controllers\Api\Credentials;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Examinations\Signature;
use App\Models\Students\Certificate;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CertificateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Certificate::with('student');

        if ($studentId = $request->query('student_id')) {
            $query->where('student_id', $studentId);
        }
        if ($type = $request->query('type')) {
            $query->type($type);
        }

        $perPage = min((int) $request->query('per_page', 50), 200);
        $page = $query->orderByDesc('id')->paginate($perPage);

        return ApiResponse::success($page->items(), 'Certificates retrieved.', ['pagination' => [
            'total' => $page->total(), 'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate($this->rules($branchId));

        $certificate = Certificate::create($data + [
            'certificate_number' => $this->nextNumber($branchId),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return ApiResponse::success($certificate->load('student'), 'Certificate issued.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $certificate = Certificate::findOrFail($id);
        $data = $request->validate($this->rules($branchId));

        $certificate->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success($certificate, 'Certificate updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Certificate::findOrFail($id)->delete();

        return ApiResponse::success(null, 'Certificate deleted.');
    }

    public function show(int $id): JsonResponse
    {
        $certificate = Certificate::with(['student', 'academicYear', 'classConfig.schoolClass', 'classConfig.section'])->findOrFail($id);

        return ApiResponse::success([
            'certificate' => $certificate,
            'branch' => Branch::find(app(BranchContext::class)->idOrFail()),
            'signatures' => Signature::where('status', true)->orderBy('serial')->get(),
        ], 'Certificate retrieved.');
    }

    private function rules(int $branchId): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('branch_id', $branchId)],
            'certificate_type' => ['required', Rule::in(['academic', 'sports', 'cultural', 'attendance', 'other'])],
            'issue_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
            'class_config_id' => ['nullable', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
        ];
    }

    private function nextNumber(int $branchId): string
    {
        $count = Certificate::withoutBranchScope()->where('branch_id', $branchId)->count();

        return sprintf('CERT-%06d', $count + 1);
    }
}
