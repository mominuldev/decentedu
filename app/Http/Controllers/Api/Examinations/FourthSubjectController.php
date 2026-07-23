<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Models\Examinations\StudentFourthSubject;
use App\Models\Students\Enrollment;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Per-student 4th/optional subject assignment for a session (contributes a GPA bonus). */
class FourthSubjectController extends Controller
{
    /** Roster for a class_config (+ optional group) with current assignment, if any. */
    public function index(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'group_id' => ['nullable', 'integer'],
        ]);

        $enrollments = Enrollment::with('student')
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('class_config_id', $data['class_config_id'])
            ->when(! empty($data['group_id']), fn ($q) => $q->where('group_id', $data['group_id']))
            ->current()
            ->get()
            ->sortBy('roll');

        $assigned = StudentFourthSubject::where('academic_year_id', $data['academic_year_id'])
            ->where('class_config_id', $data['class_config_id'])
            ->get()
            ->keyBy('student_id');

        $rows = $enrollments->map(function (Enrollment $e) use ($assigned) {
            $a = $assigned->get($e->student_id);

            return [
                'student_id' => $e->student_id,
                'roll' => $e->roll,
                'name' => $e->student?->name,
                'group_id' => $e->group_id,
                'subject_id' => $a?->subject_id,
            ];
        })->values();

        return ApiResponse::success($rows, 'Roster retrieved.');
    }

    /** Bulk assign the 4th subject to a list of students. */
    public function save(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('branch_id', $branchId)],
            'assignments.*.subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('branch_id', $branchId)],
        ]);

        $userId = auth()->id();
        foreach ($data['assignments'] as $a) {
            StudentFourthSubject::updateOrCreate(
                [
                    'student_id' => $a['student_id'],
                    'academic_year_id' => $data['academic_year_id'],
                ],
                [
                    'class_config_id' => $data['class_config_id'],
                    'subject_id' => $a['subject_id'],
                    'created_by' => $userId,
                ],
            );
        }

        return ApiResponse::success(null, 'Fourth subject assignments saved.');
    }
}
