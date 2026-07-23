<?php

namespace App\Http\Controllers\Api\Examinations;

use App\Http\Controllers\Controller;
use App\Models\Academic\ClassConfig;
use App\Models\Examinations\ExamConfig;
use App\Models\Examinations\Grade;
use App\Models\Examinations\Mark;
use App\Models\Examinations\MarkConfig;
use App\Models\Examinations\StudentExamResult;
use App\Models\Examinations\StudentExamSummary;
use App\Models\Examinations\StudentFourthSubject;
use App\Models\Students\Enrollment;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use App\Support\Reporting\Definitions\FailListReport;
use App\Support\Reporting\Definitions\MarksheetReport;
use App\Support\Reporting\Definitions\MeritListReport;
use App\Support\Reporting\Definitions\TabulationSheetReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Result processing pipeline:
 *  1. General process  — sums each subject's mark_config components into a per-subject
 *     grade for one exam (from raw `marks`).
 *  2. Final process    — combines the exams listed in the class's ExamConfig (equal
 *     weight average of %) into a per-subject grade for a combined exam (e.g. Grand Final).
 *     [inferred: legacy weighting between component exams was not confirmed — equal
 *     weight is the documented default, see docs/10 open question #7]
 *  3. Merit process     — rolls per-subject results into a per-student GPA (with the
 *     Bangladesh-board 4th-subject bonus: max(0, gp-2)) and class/section positions.
 */
class ResultController extends Controller
{
    public function generalProcess(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)],
            'group_id' => ['nullable', 'integer'],
        ]);

        $classConfig = ClassConfig::findOrFail($data['class_config_id']);
        $students = Enrollment::where('class_config_id', $data['class_config_id'])
            ->when(! empty($data['group_id']), fn ($q) => $q->where('group_id', $data['group_id']))
            ->current()->pluck('student_id');

        $configs = MarkConfig::where('class_config_id', $data['class_config_id'])
            ->where('exam_id', $data['exam_id'])
            ->where('group_id', $data['group_id'] ?? null)
            ->where('status', true)
            ->get()
            ->groupBy('subject_id');

        abort_if($configs->isEmpty(), 422, 'No mark configuration found for this exam/class.');

        $processed = 0;
        DB::transaction(function () use ($configs, $students, $data, $classConfig, &$processed) {
            foreach ($students as $studentId) {
                foreach ($configs as $subjectId => $subjectConfigs) {
                    $marks = Mark::where('student_id', $studentId)
                        ->whereIn('mark_config_id', $subjectConfigs->pluck('id'))
                        ->get();

                    if ($marks->isEmpty()) {
                        continue; // not yet entered
                    }

                    $this->upsertSubjectResult(
                        studentId: $studentId,
                        examId: $data['exam_id'],
                        subjectId: $subjectId,
                        classConfigId: $data['class_config_id'],
                        classId: $classConfig->class_id,
                        totalMarks: (float) $subjectConfigs->sum('total_marks'),
                        totalPassMark: (float) $subjectConfigs->sum('pass_mark'),
                        isAbsent: $marks->contains('is_absent', true),
                        obtainedMarks: (float) $marks->sum('obtained'),
                    );
                    $processed++;
                }
            }
        });

        return ApiResponse::success(['subject_results_processed' => $processed], 'General process completed.');
    }

    public function finalProcess(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)], // the target combined exam
        ]);

        $classConfig = ClassConfig::findOrFail($data['class_config_id']);
        $examConfig = ExamConfig::where('class_id', $classConfig->class_id)->with('exams')->first();
        abort_if(! $examConfig, 422, 'No exam config found for this class — configure which exams to combine first.');

        $componentExamIds = $examConfig->exams->pluck('id');
        abort_if($componentExamIds->isEmpty(), 422, 'The class exam config has no component exams selected.');

        $rows = StudentExamResult::where('class_config_id', $data['class_config_id'])
            ->whereIn('exam_id', $componentExamIds)
            ->get()
            ->groupBy(fn (StudentExamResult $r) => $r->student_id.':'.$r->subject_id);

        $processed = 0;
        DB::transaction(function () use ($rows, $data, $classConfig, &$processed) {
            foreach ($rows as $key => $group) {
                [$studentId, $subjectId] = explode(':', $key);
                $anyAbsent = $group->contains('is_absent', true);

                // Equal-weight average of each component exam's percentage.
                $avgPercent = $group->avg(fn (StudentExamResult $r) => $r->total_marks > 0 ? ($r->obtained_marks / $r->total_marks) * 100 : 0);
                $totalMarks = (float) $group->avg('total_marks');
                $passPercent = $group->avg(fn (StudentExamResult $r) => $r->total_marks > 0 ? ($this->subjectPassMark($r) / $r->total_marks) * 100 : 0);

                $this->upsertSubjectResult(
                    studentId: (int) $studentId,
                    examId: $data['exam_id'],
                    subjectId: (int) $subjectId,
                    classConfigId: $data['class_config_id'],
                    classId: $classConfig->class_id,
                    totalMarks: $totalMarks,
                    totalPassMark: ($passPercent / 100) * $totalMarks,
                    isAbsent: $anyAbsent,
                    obtainedMarks: ($avgPercent / 100) * $totalMarks,
                );
                $processed++;
            }
        });

        return ApiResponse::success(['subject_results_processed' => $processed], 'Final process completed.');
    }

    public function meritProcess(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('branch_id', $branchId)],
            'exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)],
        ]);

        $examConfig = ExamConfig::where('class_id', $data['class_id'])->first();
        $meritBasis = $examConfig->merit_basis ?? 'total_mark';
        $sequential = $examConfig->merit_sequential ?? true;

        $classConfigIds = ClassConfig::where('class_id', $data['class_id'])->pluck('id');

        $results = StudentExamResult::whereIn('class_config_id', $classConfigIds)
            ->where('exam_id', $data['exam_id'])
            ->get()
            ->groupBy('student_id');

        $fourthSubjects = StudentFourthSubject::whereIn('student_id', $results->keys())->get()->keyBy('student_id');

        $summaries = collect();
        DB::transaction(function () use ($results, $data, $fourthSubjects, &$summaries) {
            foreach ($results as $studentId => $subjects) {
                $classConfigId = $subjects->first()->class_config_id;
                $totalMarks = (float) $subjects->sum('total_marks');
                $totalObtained = (float) $subjects->sum('obtained_marks');
                $failedCount = $subjects->where('is_pass', false)->count() + $subjects->where('is_absent', true)->count();
                $isPass = $failedCount === 0;

                $fourthSubjectId = $fourthSubjects->get($studentId)?->subject_id;
                $compulsory = $subjects->reject(fn (StudentExamResult $r) => $r->subject_id === $fourthSubjectId);
                $fourth = $subjects->first(fn (StudentExamResult $r) => $r->subject_id === $fourthSubjectId);

                $gpa = null;
                if ($compulsory->isNotEmpty()) {
                    $base = (float) $compulsory->sum('grade_point');
                    $bonus = $fourth ? max(0, (float) $fourth->grade_point - 2.0) : 0;
                    $gpa = round(($base + $bonus) / $compulsory->count(), 2);
                }

                $summary = StudentExamSummary::updateOrCreate(
                    ['student_id' => $studentId, 'exam_id' => $data['exam_id']],
                    [
                        'class_config_id' => $classConfigId,
                        'total_marks' => $totalMarks,
                        'total_obtained' => $totalObtained,
                        'gpa' => $gpa,
                        'is_pass' => $isPass,
                        'failed_subjects_count' => $failedCount,
                        'processed_at' => now(),
                    ],
                );
                $summaries->push($summary);
            }
        });

        $this->assignPositions($summaries, $meritBasis, $sequential);

        return ApiResponse::success(['students_processed' => $summaries->count()], 'Merit process completed.');
    }

    /** Rank within the whole class (class_position) and within each section/class_config (section_position). */
    private function assignPositions($summaries, string $meritBasis, bool $sequential): void
    {
        $metric = fn (StudentExamSummary $s) => $meritBasis === 'grade_point' ? (float) ($s->gpa ?? 0) : (float) $s->total_obtained;

        $rank = function ($ordered) use ($metric, $sequential) {
            $positions = [];
            $rank = 0;
            $prevMetric = null;
            foreach ($ordered->values() as $i => $s) {
                if ($sequential || $prevMetric === null || $metric($s) !== $prevMetric) {
                    $rank = $i + 1;
                }
                $positions[$s->id] = $rank;
                $prevMetric = $metric($s);
            }

            return $positions;
        };

        $classOrdered = $summaries->filter(fn (StudentExamSummary $s) => $s->is_pass)->sortByDesc($metric);
        $classPositions = $rank($classOrdered);

        foreach ($summaries->groupBy('class_config_id') as $sectionSummaries) {
            $sectionOrdered = $sectionSummaries->filter(fn (StudentExamSummary $s) => $s->is_pass)->sortByDesc($metric);
            $sectionPositions = $rank($sectionOrdered);

            foreach ($sectionSummaries as $s) {
                $s->update([
                    'class_position' => $classPositions[$s->id] ?? null,
                    'section_position' => $sectionPositions[$s->id] ?? null,
                ]);
            }
        }
    }

    private function subjectPassMark(StudentExamResult $r): float
    {
        return (float) MarkConfig::where('subject_id', $r->subject_id)
            ->where('exam_id', $r->exam_id)
            ->where('class_config_id', $r->class_config_id)
            ->sum('pass_mark');
    }

    private function upsertSubjectResult(
        int $studentId, int $examId, int $subjectId, int $classConfigId, int $classId,
        float $totalMarks, float $totalPassMark, bool $isAbsent, float $obtainedMarks,
    ): void {
        $percentage = $totalMarks > 0 ? ($obtainedMarks / $totalMarks) * 100 : 0;
        $grade = Grade::forPercentage($classId, $percentage);
        $isPass = ! $isAbsent && $obtainedMarks >= $totalPassMark;

        StudentExamResult::updateOrCreate(
            ['student_id' => $studentId, 'exam_id' => $examId, 'subject_id' => $subjectId],
            [
                'class_config_id' => $classConfigId,
                'total_marks' => $totalMarks,
                'obtained_marks' => $isAbsent ? 0 : $obtainedMarks,
                'grade_id' => $grade?->id,
                'grade_point' => $isAbsent ? 0 : $grade?->grade_point,
                'is_pass' => $isPass,
                'is_absent' => $isAbsent,
                'processed_at' => now(),
            ],
        );
    }

    /** Per-student, per-subject breakdown for one class_config x exam — the printable marksheet. */
    public function marksheet(Request $request): JsonResponse
    {
        $definition = app(MarksheetReport::class);
        $data = $definition->data($request->validate($definition->rules()));

        return ApiResponse::success($data['rows'], 'Marksheet retrieved.');
    }

    /** Subject x student matrix for one class_config x exam. */
    public function tabulationSheet(Request $request): JsonResponse
    {
        $definition = app(TabulationSheetReport::class);
        $data = $definition->data($request->validate($definition->rules()));

        return ApiResponse::success(['subjects' => $data['subjects'], 'rows' => $data['rows']], 'Tabulation sheet retrieved.');
    }

    public function meritList(Request $request): JsonResponse
    {
        $definition = app(MeritListReport::class);
        $data = $definition->data($request->validate($definition->rules()));

        return ApiResponse::success($data['rows'], 'Merit list retrieved.');
    }

    public function failList(Request $request): JsonResponse
    {
        $definition = app(FailListReport::class);
        $data = $definition->data($request->validate($definition->rules()));

        return ApiResponse::success($data['rows'], 'Fail list retrieved.');
    }
}
