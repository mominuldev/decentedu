<?php

namespace App\Http\Controllers\Api\Students;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Students\Student;
use App\Models\Students\Enrollment;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use App\Support\Query\Includes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // currentEnrollment.classConfig + guardians are needed by the existing list view (guardian
        // column); documents is genuinely optional — StudentResource already guards it with
        // relationLoaded(), it just was never made opt-in at the query level until now.
        $with = array_merge(['currentEnrollment.classConfig', 'guardians'], Includes::resolve($request, ['documents']));
        $query = Student::query()->with($with);

        // Search
        if ($search = trim((string) $request->query('search'))) {
            $query->search($search);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter by current class
        if ($request->has('class_config_id')) {
            $query->whereHas('currentEnrollment', function ($q) use ($request) {
                $q->where('class_config_id', $request->query('class_config_id'));
            });
        }

        // Filter by academic year
        if ($request->has('academic_year_id')) {
            $query->whereHas('enrollments', function ($q) use ($request) {
                $q->where('academic_year_id', $request->query('academic_year_id'));
            });
        }

        // Sorting
        $sort = $request->query('sort', 'name');
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        if (!in_array($col, ['name', 'student_uid', 'created_at'], true)) {
            $col = 'name';
        }
        $query->orderBy($col, $dir);

        // Pagination
        $perPage = min((int) $request->query('per_page', 25), 200);
        $students = $query->paginate($perPage);

        return ApiResponse::success(
            StudentResource::collection($students),
            'Students retrieved successfully.',
            [
                'pagination' => [
                    'total' => $students->total(),
                    'per_page' => $students->perPage(),
                    'current_page' => $students->currentPage(),
                    'last_page' => $students->lastPage(),
                ]
            ]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'student_uid' => "required|string|unique:students,student_uid,NULL,id,branch_id,{$branchId}",
            'name' => 'required|string|max:255',
            'name_bn' => 'nullable|string|max:255',
            'sex' => 'required|in:male,female,other',
            'religion' => 'nullable|string|max:100',
            'blood_group' => 'nullable|string|max:10',
            'dob' => 'nullable|date',
            'fathers_name' => 'required|string|max:255',
            'mothers_name' => 'required|string|max:255',
            'mobile' => 'nullable|string|max:20',
            'father_mobile' => 'nullable|string|max:20',
            'mother_mobile' => 'nullable|string|max:20',
            'photo_path' => 'nullable|string|max:500',
            'present_address' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'status' => 'in:active,transferred,left,passed_out',

            // Initial enrollment data
            'academic_year_id' => 'required|exists:academic_years,id',
            'class_config_id' => 'required|exists:class_configs,id',
            'group_id' => 'nullable|exists:groups,id',
            'category_id' => 'nullable|exists:categories,id',
            'roll' => 'required|string|max:50',

            // Guardians data
            'guardians' => 'nullable|array',
            'guardians.*.relationship' => 'required|in:father,mother,guardian,other',
            'guardians.*.name' => 'required|string|max:255',
            'guardians.*.mobile' => 'nullable|string|max:20',
            'guardians.*.email' => 'nullable|email|max:255',
            'guardians.*.address' => 'nullable|string',
            'guardians.*.occupation' => 'nullable|string|max:255',
            'guardians.*.nid' => 'nullable|string|max:50',
            'guardians.*.is_emergency_contact' => 'boolean',
        ]);

        $student = Student::create([
            'branch_id' => $branchId,
            'student_uid' => $data['student_uid'],
            'name' => $data['name'],
            'name_bn' => $data['name_bn'] ?? null,
            'sex' => $data['sex'],
            'religion' => $data['religion'] ?? null,
            'blood_group' => $data['blood_group'] ?? null,
            'dob' => $data['dob'] ?? null,
            'fathers_name' => $data['fathers_name'],
            'mothers_name' => $data['mothers_name'],
            'mobile' => $data['mobile'] ?? null,
            'father_mobile' => $data['father_mobile'] ?? null,
            'mother_mobile' => $data['mother_mobile'] ?? null,
            'photo_path' => $data['photo_path'] ?? null,
            'present_address' => $data['present_address'] ?? null,
            'permanent_address' => $data['permanent_address'] ?? null,
            'status' => $data['status'] ?? 'active',
            'created_by' => auth()->id(),
        ]);

        // Create initial enrollment
        Enrollment::create([
            'branch_id' => $branchId,
            'student_id' => $student->id,
            'academic_year_id' => $data['academic_year_id'],
            'class_config_id' => $data['class_config_id'],
            'group_id' => $data['group_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'roll' => $data['roll'],
            'is_current' => true,
            'enrolled_at' => now(),
            'created_by' => auth()->id(),
        ]);

        // Create guardians if provided
        if (!empty($data['guardians'])) {
            foreach ($data['guardians'] as $guardianData) {
                $student->guardians()->create([
                    'branch_id' => $branchId,
                    'relationship' => $guardianData['relationship'],
                    'name' => $guardianData['name'],
                    'mobile' => $guardianData['mobile'] ?? null,
                    'email' => $guardianData['email'] ?? null,
                    'address' => $guardianData['address'] ?? null,
                    'occupation' => $guardianData['occupation'] ?? null,
                    'nid' => $guardianData['nid'] ?? null,
                    'is_emergency_contact' => $guardianData['is_emergency_contact'] ?? false,
                    'created_by' => auth()->id(),
                ]);
            }
        }

        return ApiResponse::success(
            new StudentResource($student->load(['currentEnrollment', 'guardians'])),
            'Student created successfully.',
            status: 201
        );
    }

    public function show(int $id): JsonResponse
    {
        $student = Student::with(['enrollments', 'currentEnrollment.classConfig', 'guardians', 'documents'])
            ->findOrFail($id);

        return ApiResponse::success(
            new StudentResource($student),
            'Student retrieved successfully.'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $student = Student::findOrFail($id);

        $data = $request->validate([
            'student_uid' => "sometimes|required|string|unique:students,student_uid,{$id},id,branch_id,{$branchId}",
            'name' => 'sometimes|required|string|max:255',
            'name_bn' => 'nullable|string|max:255',
            'sex' => 'sometimes|required|in:male,female,other',
            'religion' => 'nullable|string|max:100',
            'blood_group' => 'nullable|string|max:10',
            'dob' => 'nullable|date',
            'fathers_name' => 'sometimes|required|string|max:255',
            'mothers_name' => 'sometimes|required|string|max:255',
            'mobile' => 'nullable|string|max:20',
            'father_mobile' => 'nullable|string|max:20',
            'mother_mobile' => 'nullable|string|max:20',
            'photo_path' => 'nullable|string|max:500',
            'present_address' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'status' => 'in:active,transferred,left,passed_out',
        ]);

        $student->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success(
            new StudentResource($student->load(['currentEnrollment', 'guardians'])),
            'Student updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $student = Student::findOrFail($id);
        $student->delete();

        return ApiResponse::success(null, 'Student deleted successfully.');
    }

    /**
     * Bulk register students
     */
    public function bulkRegister(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'class_config_id' => 'required|exists:class_configs,id',
            'students' => 'required|array|max:100',
            'students.*.student_uid' => "required|string|unique:students,student_uid,NULL,id,branch_id,{$branchId}",
            'students.*.name' => 'required|string|max:255',
            'students.*.sex' => 'required|in:male,female,other',
            'students.*.fathers_name' => 'required|string|max:255',
            'students.*.mothers_name' => 'required|string|max:255',
            'students.*.roll' => 'required|string|max:50',
            'students.*.mobile' => 'nullable|string|max:20',
        ]);

        $created = [];
        $failed = [];

        foreach ($data['students'] as $index => $studentData) {
            try {
                $student = Student::create([
                    'branch_id' => $branchId,
                    'student_uid' => $studentData['student_uid'],
                    'name' => $studentData['name'],
                    'sex' => $studentData['sex'],
                    'fathers_name' => $studentData['fathers_name'],
                    'mothers_name' => $studentData['mothers_name'],
                    'mobile' => $studentData['mobile'] ?? null,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                ]);

                Enrollment::create([
                    'branch_id' => $branchId,
                    'student_id' => $student->id,
                    'academic_year_id' => $data['academic_year_id'],
                    'class_config_id' => $data['class_config_id'],
                    'roll' => $studentData['roll'],
                    'is_current' => true,
                    'enrolled_at' => now(),
                    'created_by' => auth()->id(),
                ]);

                $created[] = ['index' => $index, 'student_uid' => $student->student_uid, 'id' => $student->id];
            } catch (\Exception $e) {
                $failed[] = ['index' => $index, 'student_uid' => $studentData['student_uid'], 'error' => $e->getMessage()];
            }
        }

        return ApiResponse::success([
            'created' => $created,
            'failed' => $failed,
            'summary' => [
                'total' => count($data['students']),
                'created_count' => count($created),
                'failed_count' => count($failed),
            ]
        ], 'Bulk registration completed.');
    }

    /**
     * Migrate students (promote/push-back)
     */
    public function migrate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_academic_year_id' => 'required|exists:academic_years,id',
            'to_academic_year_id' => 'required|exists:academic_years,id',
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
            'migration_type' => 'required|in:promote,push_back,general',
        ]);

        // Implementation would go here
        // This would involve:
        // 1. Get current enrollments for the students in from_academic_year_id
        // 2. Create new enrollments in to_academic_year_id
        // 3. Handle roll numbers, class changes based on migration type
        // 4. Update is_current flags

        return ApiResponse::success(['message' => 'Migration functionality to be implemented']);
    }
}