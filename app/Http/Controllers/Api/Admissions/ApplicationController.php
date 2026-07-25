<?php

namespace App\Http\Controllers\Api\Admissions;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdmissionApplicationResource;
use App\Models\Admissions\AdmissionApplication;
use App\Models\Students\Enrollment;
use App\Models\Students\Student;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    /** Bio fields shared between an application and the student it converts into. */
    private const BIO_RULES = [
        'name' => 'required|string|max:255',
        'name_bn' => 'nullable|string|max:255',
        'sex' => 'required|in:male,female,other',
        'religion' => 'nullable|in:islam,hindu,christian,buddhist,others',
        'blood_group' => 'nullable|string|max:10',
        'dob' => 'nullable|date',
        'fathers_name' => 'required|string|max:255',
        'mothers_name' => 'required|string|max:255',
        'mobile' => 'nullable|string|max:20',
        'guardian_mobile' => 'nullable|string|max:20',
        'photo_path' => 'nullable|string|max:500',
        'present_address' => 'nullable|string',
        'permanent_address' => 'nullable|string',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = AdmissionApplication::query()
            ->with(['admissionYear', 'classConfig.schoolClass', 'classConfig.section', 'classConfig.shift', 'quota']);

        if ($search = trim((string) $request->query('search'))) {
            $query->search($search);
        }

        foreach (['admission_year_id', 'class_config_id', 'quota_id', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        // Merit-oriented default sort: highest score first, then application number.
        $sort = $request->query('sort', '-score');
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        if (! in_array($col, ['score', 'name', 'application_no', 'created_at'], true)) {
            $col = 'score';
        }
        $query->orderBy($col, $dir)->orderBy('application_no');

        $perPage = min((int) $request->query('per_page', 25), 200);
        $applications = $query->paginate($perPage);

        return ApiResponse::success(
            AdmissionApplicationResource::collection($applications),
            'Applications retrieved.',
            ['pagination' => [
                'total' => $applications->total(),
                'per_page' => $applications->perPage(),
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
            ]]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate(self::BIO_RULES + [
            'admission_year_id' => 'required|exists:admission_years,id',
            'class_config_id' => 'required|exists:class_configs,id',
            'quota_id' => 'nullable|exists:quotas,id',
            'application_no' => "nullable|string|max:100|unique:admission_applications,application_no,NULL,id,branch_id,{$branchId},deleted_at,NULL",
            'score' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:pending,selected,waiting,rejected',
            'applied_at' => 'nullable|date',
            'remarks' => 'nullable|string|max:255',
        ]);

        $application = AdmissionApplication::create($data + [
            'branch_id' => $branchId,
            'application_no' => $data['application_no'] ?? $this->nextApplicationNo($branchId),
            'status' => $data['status'] ?? 'pending',
            'applied_at' => $data['applied_at'] ?? now()->toDateString(),
            'created_by' => auth()->id(),
        ]);

        return ApiResponse::success(
            new AdmissionApplicationResource($application->load(['admissionYear', 'classConfig', 'quota'])),
            'Application created.',
            status: 201
        );
    }

    public function show(int $id): JsonResponse
    {
        $application = AdmissionApplication::with([
            'admissionYear', 'classConfig.schoolClass', 'classConfig.section', 'classConfig.shift', 'quota', 'student',
        ])->findOrFail($id);

        return ApiResponse::success(new AdmissionApplicationResource($application), 'Application retrieved.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $application = AdmissionApplication::findOrFail($id);

        if ($application->student_id) {
            return ApiResponse::error('This applicant has already been admitted and cannot be edited.', 'ALREADY_ADMITTED', 409);
        }

        $bioRules = array_map(fn ($rule) => 'sometimes|'.$rule, self::BIO_RULES);

        $data = $request->validate($bioRules + [
            'admission_year_id' => 'sometimes|exists:admission_years,id',
            'class_config_id' => 'sometimes|exists:class_configs,id',
            'quota_id' => 'nullable|exists:quotas,id',
            'application_no' => "sometimes|string|max:100|unique:admission_applications,application_no,{$id},id,branch_id,{$branchId},deleted_at,NULL",
            'score' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:pending,selected,waiting,rejected',
            'applied_at' => 'nullable|date',
            'remarks' => 'nullable|string|max:255',
        ]);

        $application->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success(
            new AdmissionApplicationResource($application->fresh(['admissionYear', 'classConfig', 'quota'])),
            'Application updated.'
        );
    }

    /** Move an application through the pending → selected/waiting/rejected pipeline. */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $application = AdmissionApplication::findOrFail($id);

        if ($application->student_id) {
            return ApiResponse::error('This applicant has already been admitted.', 'ALREADY_ADMITTED', 409);
        }

        $data = $request->validate([
            'status' => 'required|in:pending,selected,waiting,rejected',
            'remarks' => 'nullable|string|max:255',
        ]);

        $application->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success(
            new AdmissionApplicationResource($application->fresh(['admissionYear', 'classConfig', 'quota'])),
            'Application status updated.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $application = AdmissionApplication::findOrFail($id);

        if ($application->student_id) {
            return ApiResponse::error('This applicant has already been admitted and cannot be deleted.', 'ALREADY_ADMITTED', 409);
        }

        $application->delete();

        return ApiResponse::success(null, 'Application deleted.');
    }

    /**
     * Convert an admitted-selected applicant into a Student + first Enrollment. This is the
     * hand-off from the admission pipeline to the Students module (docs/02 §Admission).
     */
    public function convert(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $application = AdmissionApplication::with('admissionYear')->findOrFail($id);

        if ($application->student_id) {
            return ApiResponse::error('This applicant has already been converted to a student.', 'ALREADY_ADMITTED', 409);
        }

        if ($application->status === 'rejected') {
            return ApiResponse::error('A rejected application cannot be converted to a student.', 'APPLICATION_REJECTED', 422);
        }

        $data = $request->validate([
            'student_uid' => "required|string|max:255|unique:students,student_uid,NULL,id,branch_id,{$branchId}",
            'academic_year_id' => 'required|exists:academic_years,id',
            'class_config_id' => 'nullable|exists:class_configs,id',
            'group_id' => 'nullable|exists:groups,id',
            'category_id' => 'nullable|exists:categories,id',
            'roll' => 'required|string|max:50',
        ]);

        $student = DB::transaction(function () use ($application, $branchId, $data) {
            $student = Student::create([
                'branch_id' => $branchId,
                'student_uid' => $data['student_uid'],
                'name' => $application->name,
                'name_bn' => $application->name_bn,
                'sex' => $application->sex,
                'religion' => $application->religion,
                'blood_group' => $application->blood_group,
                'dob' => $application->dob,
                'fathers_name' => $application->fathers_name,
                'mothers_name' => $application->mothers_name,
                'mobile' => $application->mobile,
                'father_mobile' => $application->guardian_mobile,
                'present_address' => $application->present_address,
                'permanent_address' => $application->permanent_address,
                'photo_path' => $application->photo_path,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            Enrollment::create([
                'branch_id' => $branchId,
                'student_id' => $student->id,
                'academic_year_id' => $data['academic_year_id'],
                'class_config_id' => $data['class_config_id'] ?? $application->class_config_id,
                'group_id' => $data['group_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'roll' => $data['roll'],
                'is_current' => true,
                'enrolled_at' => now(),
                'created_by' => auth()->id(),
            ]);

            $application->update([
                'status' => 'admitted',
                'student_id' => $student->id,
                'updated_by' => auth()->id(),
            ]);

            return $student;
        });

        return ApiResponse::success(
            [
                'student_id' => $student->id,
                'student_uid' => $student->student_uid,
                'application' => new AdmissionApplicationResource(
                    $application->fresh(['admissionYear', 'classConfig', 'quota'])
                ),
            ],
            'Applicant admitted and converted to a student.',
            status: 201
        );
    }

    /** KPI counts for the admissions dashboard, optionally scoped to one admission year. */
    public function stats(Request $request): JsonResponse
    {
        $base = AdmissionApplication::query();
        if ($request->filled('admission_year_id')) {
            $base->where('admission_year_id', $request->query('admission_year_id'));
        }

        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return ApiResponse::success([
            'total' => (int) $byStatus->sum(),
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'selected' => (int) ($byStatus['selected'] ?? 0),
            'waiting' => (int) ($byStatus['waiting'] ?? 0),
            'rejected' => (int) ($byStatus['rejected'] ?? 0),
            'admitted' => (int) ($byStatus['admitted'] ?? 0),
        ], 'Admission stats retrieved.');
    }

    /** Sequential per-branch application number: APP-0001, APP-0002, … */
    private function nextApplicationNo(int $branchId): string
    {
        $count = AdmissionApplication::withTrashed()
            ->where('branch_id', $branchId)
            ->count();

        return 'APP-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
