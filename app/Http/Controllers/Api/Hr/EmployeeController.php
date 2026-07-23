<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\SubjectTeacherResource;
use App\Models\Hr\Employee;
use App\Models\Hr\SubjectTeacher;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()->with(['designation', 'hrSection']);

        // Search
        if ($search = trim((string) $request->query('search'))) {
            $query->search($search);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter by designation
        if ($request->has('designation_id')) {
            $query->where('designation_id', $request->query('designation_id'));
        }

        // Filter by department
        if ($request->has('hr_section_id')) {
            $query->where('hr_section_id', $request->query('hr_section_id'));
        }

        // Filter by employment type
        if ($request->has('employment_type')) {
            $query->where('employment_type', $request->query('employment_type'));
        }

        // Teachers only
        if ($request->query('teachers_only') === 'true') {
            $query->teachers();
        }

        // Sorting
        $sort = $request->query('sort', 'name');
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        if (!in_array($col, ['name', 'employee_uid', 'joining_date', 'created_at'], true)) {
            $col = 'name';
        }
        $query->orderBy($col, $dir);

        // Pagination
        $perPage = min((int) $request->query('per_page', 25), 200);
        $employees = $query->paginate($perPage);

        return ApiResponse::success(
            EmployeeResource::collection($employees),
            'Employees retrieved successfully.',
            [
                'pagination' => [
                    'total' => $employees->total(),
                    'per_page' => $employees->perPage(),
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                ]
            ]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $data = $request->validate([
            'employee_uid' => "required|string|unique:employees,employee_uid,NULL,id,branch_id,{$branchId}",
            'name' => 'required|string|max:255',
            'name_bn' => 'nullable|string|max:255',
            'designation_id' => 'required|exists:designations,id',
            'hr_section_id' => 'nullable|exists:hr_sections,id',
            'sex' => 'required|in:male,female,other',
            'religion' => 'nullable|string|max:100',
            'dob' => 'nullable|date',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'nid' => 'nullable|string|max:50',
            'photo_path' => 'nullable|string|max:500',
            'present_address' => 'nullable|string',
            'permanent_address' => 'nullable|string',
            'joining_date' => 'required|date',
            'employment_type' => 'in:permanent,contract,temporary',
            'qualifications' => 'nullable|array',

            // Subject assignments for teachers
            'subject_assignments' => 'nullable|array',
            'subject_assignments.*.subject_id' => 'required|exists:subjects,id',
            'subject_assignments.*.class_config_id' => 'required|exists:class_configs,id',
        ]);

        $employee = Employee::create([
            'branch_id' => $branchId,
            'employee_uid' => $data['employee_uid'],
            'name' => $data['name'],
            'name_bn' => $data['name_bn'] ?? null,
            'designation_id' => $data['designation_id'],
            'hr_section_id' => $data['hr_section_id'] ?? null,
            'sex' => $data['sex'],
            'religion' => $data['religion'] ?? null,
            'dob' => $data['dob'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'nid' => $data['nid'] ?? null,
            'photo_path' => $data['photo_path'] ?? null,
            'present_address' => $data['present_address'] ?? null,
            'permanent_address' => $data['permanent_address'] ?? null,
            'joining_date' => $data['joining_date'],
            'employment_type' => $data['employment_type'] ?? 'permanent',
            'qualifications' => $data['qualifications'] ?? null,
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);

        // Create subject assignments if provided
        if (!empty($data['subject_assignments'])) {
            foreach ($data['subject_assignments'] as $assignment) {
                SubjectTeacher::create([
                    'branch_id' => $branchId,
                    'employee_id' => $employee->id,
                    'subject_id' => $assignment['subject_id'],
                    'class_config_id' => $assignment['class_config_id'],
                    'is_active' => true,
                    'created_by' => auth()->id(),
                ]);
            }
        }

        return ApiResponse::success(
            new EmployeeResource($employee->load(['designation', 'hrSection', 'subjectTeachers'])),
            'Employee created successfully.',
            status: 201
        );
    }

    public function show(int $id): JsonResponse
    {
        $employee = Employee::with(['designation', 'hrSection', 'subjectTeachers.subject', 'subjectTeachers.classConfig'])
            ->findOrFail($id);

        return ApiResponse::success(
            new EmployeeResource($employee),
            'Employee retrieved successfully.'
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'employee_uid' => "sometimes|required|string|unique:employees,employee_uid,{$id},id,branch_id,{$branchId}",
            'name' => 'sometimes|required|string|max:255',
            'name_bn' => 'nullable|string|max:255',
            'designation_id' => 'sometimes|required|exists:designations,id',
            'hr_section_id' => 'nullable|exists:hr_sections,id',
            'sex' => 'sometimes|required|in:male,female,other',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'status' => 'in:active,resigned,terminated,retired',
            'leaving_date' => 'nullable|date',
        ]);

        $employee->update($data + ['updated_by' => auth()->id()]);

        return ApiResponse::success(
            new EmployeeResource($employee->load(['designation', 'hrSection'])),
            'Employee updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        return ApiResponse::success(null, 'Employee deleted successfully.');
    }

    /**
     * Assign subjects to teacher
     */
    public function assignSubject(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'class_config_id' => 'required|exists:class_configs,id',
        ]);

        $assignment = SubjectTeacher::create([
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'subject_id' => $data['subject_id'],
            'class_config_id' => $data['class_config_id'],
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        return ApiResponse::success(
            new SubjectTeacherResource($assignment->load(['subject', 'classConfig'])),
            'Subject assigned successfully.',
            status: 201
        );
    }

    /**
     * Remove subject assignment
     */
    public function removeSubject(int $id, int $assignmentId): JsonResponse
    {
        $assignment = SubjectTeacher::where('employee_id', $id)->findOrFail($assignmentId);
        $assignment->delete();

        return ApiResponse::success(null, 'Subject assignment removed successfully.');
    }
}