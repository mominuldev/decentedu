<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Hr\Employee;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    /**
     * Get teachers list (employees with subject assignments)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->with(['designation', 'hrSection', 'subjectTeachers.subject', 'subjectTeachers.classConfig'])
            ->teachers(); // Only employees with subject assignments

        // Search
        if ($search = trim((string) $request->query('search'))) {
            $query->search($search);
        }

        // Filter by status (default: active only)
        $status = $request->query('status', 'active');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Filter by designation
        if ($request->has('designation_id')) {
            $query->where('designation_id', $request->query('designation_id'));
        }

        // Filter by department/section
        if ($request->has('hr_section_id')) {
            $query->where('hr_section_id', $request->query('hr_section_id'));
        }

        // Filter by employment type
        if ($request->has('employment_type')) {
            $query->where('employment_type', $request->query('employment_type'));
        }

        // Filter by subject
        if ($request->has('subject_id')) {
            $query->whereHas('subjectTeachers', function ($q) use ($request) {
                $q->where('subject_id', $request->query('subject_id'))
                  ->where('is_active', true);
            });
        }

        // Filter by class config
        if ($request->has('class_config_id')) {
            $query->whereHas('subjectTeachers', function ($q) use ($request) {
                $q->where('class_config_id', $request->query('class_config_id'))
                  ->where('is_active', true);
            });
        }

        // Sorting
        $sort = $request->query('sort', 'name');
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        if (! in_array($col, ['name', 'employee_uid', 'joining_date', 'created_at'], true)) {
            $col = 'name';
        }
        $query->orderBy($col, $dir);

        // Pagination
        $perPage = min((int) $request->query('per_page', 25), 200);
        $teachers = $query->paginate($perPage);

        return ApiResponse::success(
            EmployeeResource::collection($teachers),
            'Teachers retrieved successfully.',
            [
                'pagination' => [
                    'total' => $teachers->total(),
                    'per_page' => $teachers->perPage(),
                    'current_page' => $teachers->currentPage(),
                    'last_page' => $teachers->lastPage(),
                ],
            ]
        );
    }

    /**
     * Get single teacher details
     */
    public function show(int $id): JsonResponse
    {
        $teacher = Employee::query()
            ->with(['designation', 'hrSection', 'subjectTeachers.subject', 'subjectTeachers.classConfig.academicYear'])
            ->teachers()
            ->findOrFail($id);

        return ApiResponse::success(
            new EmployeeResource($teacher),
            'Teacher retrieved successfully.'
        );
    }

    /**
     * Get teacher's class assignments
     */
    public function classes(int $id): JsonResponse
    {
        $teacher = Employee::query()
            ->teachers()
            ->findOrFail($id);

        $classes = $teacher->subjectTeachers()
            ->with('classConfig.academicYear', 'classConfig.shift', 'classConfig.section', 'subject')
            ->where('is_active', true)
            ->get()
            ->groupBy('class_config_id');

        return ApiResponse::success(
            $classes,
            'Teacher class assignments retrieved successfully.'
        );
    }

    /**
     * Get teachers by subject
     */
    public function bySubject(Request $request, int $subjectId): JsonResponse
    {
        $query = Employee::query()
            ->with(['designation', 'hrSection'])
            ->teachers()
            ->whereHas('subjectTeachers', function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId)
                  ->where('is_active', true);
            });

        // Filter by status (default: active only)
        $status = $request->query('status', 'active');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $teachers = $query->get();

        return ApiResponse::success(
            EmployeeResource::collection($teachers),
            'Teachers by subject retrieved successfully.'
        );
    }

    /**
     * Get teachers by class config
     */
    public function byClassConfig(Request $request, int $classConfigId): JsonResponse
    {
        $query = Employee::query()
            ->with(['designation', 'hrSection'])
            ->teachers()
            ->whereHas('subjectTeachers', function ($q) use ($classConfigId) {
                $q->where('class_config_id', $classConfigId)
                  ->where('is_active', true);
            });

        // Filter by status (default: active only)
        $status = $request->query('status', 'active');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $teachers = $query->get();

        return ApiResponse::success(
            EmployeeResource::collection($teachers),
            'Teachers by class retrieved successfully.'
        );
    }

    /**
     * Get available teachers (active, no leaving date, with active subject assignments)
     */
    public function available(Request $request): JsonResponse
    {
        $query = Employee::query()
            ->with(['designation', 'hrSection'])
            ->teachers()
            ->where('status', 'active')
            ->whereNull('leaving_date');

        // Filter by subject
        if ($request->has('subject_id')) {
            $query->whereHas('subjectTeachers', function ($q) use ($request) {
                $q->where('subject_id', $request->query('subject_id'))
                  ->where('is_active', true);
            });
        }

        // Filter by class config
        if ($request->has('class_config_id')) {
            $query->whereHas('subjectTeachers', function ($q) use ($request) {
                $q->where('class_config_id', $request->query('class_config_id'))
                  ->where('is_active', true);
            });
        }

        // Simple list without pagination for dropdowns/selects
        $teachers = $query->orderBy('name')->get();

        return ApiResponse::success(
            EmployeeResource::collection($teachers),
            'Available teachers retrieved successfully.'
        );
    }
}