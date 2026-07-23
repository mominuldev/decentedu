<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Models\Examinations\Mark;
use App\Models\Examinations\MarkConfig;
use App\Models\Students\Enrollment;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Mark input/update: the class roster for one subject x exam, one row per mark_config component. */
class MarksController extends Controller
{
    /** The roster + each mark_config component for a class_config x exam x subject (x group). */
    public function grid(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)],
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('branch_id', $branchId)],
            'group_id' => ['nullable', 'integer'],
        ]);

        $markConfigs = MarkConfig::with('shortCode')
            ->where('class_config_id', $data['class_config_id'])
            ->where('exam_id', $data['exam_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('group_id', $data['group_id'] ?? null)
            ->where('status', true)
            ->orderBy('serial')
            ->get();

        abort_if($markConfigs->isEmpty(), 422, 'No mark configuration found for this subject/exam. Set up Mark Config first.');

        $enrollments = Enrollment::with('student')
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('class_config_id', $data['class_config_id'])
            ->when(! empty($data['group_id']), fn ($q) => $q->where('group_id', $data['group_id']))
            ->current()
            ->get()
            ->sortBy('roll');

        $existing = Mark::whereIn('mark_config_id', $markConfigs->pluck('id'))
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->get()
            ->groupBy('student_id');

        $students = $enrollments->map(function (Enrollment $e) use ($existing) {
            $marks = $existing->get($e->student_id, collect())->keyBy('mark_config_id');

            return [
                'student_id' => $e->student_id,
                'enrollment_id' => $e->id,
                'roll' => $e->roll,
                'name' => $e->student?->name,
                'is_absent' => $marks->first()?->is_absent ?? false,
                'marks' => $marks->map(fn (Mark $m) => $m->obtained)->toArray(),
            ];
        })->values();

        return ApiResponse::success([
            'components' => $markConfigs->map(fn (MarkConfig $m) => [
                'mark_config_id' => $m->id,
                'short_code_name' => $m->shortCode?->name,
                'total_marks' => $m->total_marks,
                'pass_mark' => $m->pass_mark,
            ]),
            'students' => $students,
        ], 'Grid retrieved.');
    }

    /** Bulk save marks for one class_config x exam x subject. */
    public function save(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('branch_id', $branchId)],
            'entries.*.is_absent' => ['sometimes', 'boolean'],
            'entries.*.marks' => ['required', 'array'],
            'entries.*.marks.*.mark_config_id' => ['required', 'integer', Rule::exists('mark_configs', 'id')->where('branch_id', $branchId)],
            'entries.*.marks.*.obtained' => ['nullable', 'numeric', 'min:0'],
        ]);

        $markConfigs = MarkConfig::whereIn(
            'id',
            collect($data['entries'])->flatMap(fn ($e) => collect($e['marks'])->pluck('mark_config_id'))->unique(),
        )->get()->keyBy('id');

        $userId = auth()->id();

        foreach ($data['entries'] as $entry) {
            $isAbsent = $entry['is_absent'] ?? false;
            $enrollment = Enrollment::where('student_id', $entry['student_id'])->current()->first();

            foreach ($entry['marks'] as $m) {
                $config = $markConfigs->get($m['mark_config_id']);
                if (! $config) {
                    continue;
                }

                $obtained = $isAbsent ? null : $m['obtained'] ?? null;
                abort_if(
                    ! $isAbsent && $obtained !== null && $obtained > $config->total_marks,
                    422,
                    "Obtained mark exceeds total marks ({$config->total_marks}) for one or more components.",
                );

                Mark::updateOrCreate(
                    ['student_id' => $entry['student_id'], 'mark_config_id' => $m['mark_config_id']],
                    [
                        'enrollment_id' => $enrollment?->id,
                        'exam_id' => $data['exam_id'],
                        'obtained' => $obtained,
                        'is_absent' => $isAbsent,
                        'marked_by' => $userId,
                    ],
                );
            }
        }

        return ApiResponse::success(null, 'Marks saved.');
    }
}
