<?php

namespace App\Http\Controllers\Api\Credentials;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Examinations\Signature;
use App\Models\Students\Student;
use App\Models\Students\TransferCertificate;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Transfer certificates are legal documents with a real side effect (the student is marked
 * transferred) — like Voucher/FeeCollection (K7), there's no update/destroy route; a correction
 * is a new record with a remark, not an edit to history.
 */
class TransferCertificateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TransferCertificate::with('student');

        if ($studentId = $request->query('student_id')) {
            $query->where('student_id', $studentId);
        }

        $perPage = min((int) $request->query('per_page', 50), 200);
        $page = $query->orderByDesc('id')->paginate($perPage);

        return ApiResponse::success($page->items(), 'Transfer certificates retrieved.', ['pagination' => [
            'total' => $page->total(), 'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('branch_id', $branchId)],
            'issue_date' => ['required', 'date'],
            'reason_for_leaving' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
        ]);

        $tc = DB::transaction(function () use ($data, $branchId) {
            $tc = TransferCertificate::create($data + [
                'certificate_number' => $this->nextNumber($branchId),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            Student::where('id', $data['student_id'])->update(['status' => 'transferred', 'updated_by' => auth()->id()]);

            return $tc;
        });

        return ApiResponse::success($tc->load('student'), 'Transfer certificate issued.', status: 201);
    }

    public function show(int $id): JsonResponse
    {
        $tc = TransferCertificate::with(['student', 'academicYear', 'classConfig.schoolClass', 'classConfig.section'])->findOrFail($id);

        return ApiResponse::success([
            'certificate' => $tc,
            'branch' => Branch::find(app(BranchContext::class)->idOrFail()),
            'signatures' => Signature::where('status', true)->orderBy('serial')->get(),
        ], 'Transfer certificate retrieved.');
    }

    private function nextNumber(int $branchId): string
    {
        $count = TransferCertificate::withoutBranchScope()->where('branch_id', $branchId)->count();

        return sprintf('TC-%06d', $count + 1);
    }
}
