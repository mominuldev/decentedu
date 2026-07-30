<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cms\Public;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\Section;
use App\Models\Branch;
use App\Models\Examinations\Exam;
use App\Models\Examinations\Grade;
use App\Models\Examinations\StudentExamResult;
use App\Models\Examinations\StudentExamSummary;
use App\Models\Students\Enrollment;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use App\Support\Reporting\Definitions\MarksheetReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unauthenticated result lookup for the public site.
 *
 * The `public-branch` middleware pins BranchContext to config('cms.public_branch_id'),
 * so the `BelongsToBranch` global scope already isolates every read below to that one
 * branch. `publicBranchId()` is only needed for models that don't use the trait
 * (Branch itself) and for building the response.
 */
class PublicResultController extends Controller
{
    /** Bangla (and Arabic-Indic) digits mapped to ASCII, so "০০১" and "001" search alike. */
    private const DIGIT_MAP = [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /** The branch whose content/results the public site serves. */
    private function publicBranchId(): int
    {
        $contextId = app(BranchContext::class)->id();

        return (int) ($contextId ?? config('cms.public_branch_id') ?? 1);
    }

    /** Get options (Years, Sections, Class Configs, Exams) for public search. */
    public function options(Request $request): JsonResponse
    {
        $academicYears = AcademicYear::where('status', true)
            ->orderByDesc('is_current')
            ->orderByDesc('id')
            ->get(['id', 'name', 'is_current']);

        // Get sections for filtering (A, B, C, D, etc.)
        $sections = Section::where('status', true)
            ->orderBy('id')
            ->get(['id', 'name']);

        $classConfigs = ClassConfig::with(['schoolClass:id,name,name_bn', 'section:id,name', 'shift:id,name'])
            ->where('status', true)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'label' => $c->label(),
                'class_name' => $c->schoolClass?->name,
                'class_name_bn' => $c->schoolClass?->name_bn,
                'section_name' => $c->section?->name,
                'section_id' => $c->section_id,
                'shift_name' => $c->shift?->name,
            ]);

        $exams = Exam::where('status', true)
            ->orderBy('id')
            ->get(['id', 'name']);

        return ApiResponse::success([
            'academic_years' => $academicYears,
            'sections' => $sections,
            'class_configs' => $classConfigs,
            'exams' => $exams,
        ], 'Result search options retrieved.');
    }

    /**
     * Headline figures for the public results page, computed from processed exam
     * summaries — the page previously hard-coded these as fixed numbers.
     *
     * Results aren't year-scoped in this schema (neither `exams` nor
     * `student_exam_summaries` carries an academic year), so these cover every
     * processed result in the branch.
     */
    public function stats(Request $request): JsonResponse
    {
        $total = StudentExamSummary::count();
        $passed = StudentExamSummary::where('is_pass', true)->count();

        // "Top GPA" is whatever this branch's own grade scale tops out at, not a fixed 5.00.
        $topGpa = Grade::where('status', true)->max('grade_point');

        return ApiResponse::success([
            'results_count' => $total,
            'pass_rate' => $total > 0 ? round($passed / $total * 100, 1) : null,
            'top_gpa' => $topGpa !== null ? (float) $topGpa : null,
            'top_gpa_count' => $topGpa !== null
                ? StudentExamSummary::where('gpa', '>=', $topGpa)->count()
                : 0,
            'published_exams_count' => StudentExamSummary::distinct()->count('exam_id'),
        ], 'Public result statistics retrieved.');
    }

    /**
     * The branch's actual grade scale. Uniform across classes in practice, so identical
     * bands are collapsed into one published reference table.
     */
    public function gradingScale(Request $request): JsonResponse
    {
        $scale = Grade::where('status', true)
            ->orderByDesc('mark_from')
            ->get(['name', 'grade_point', 'mark_from', 'mark_to'])
            ->unique(fn ($g) => $g->name.'|'.$g->mark_from.'|'.$g->mark_to)
            ->values()
            ->map(fn ($g) => [
                'name' => $g->name,
                'grade_point' => (float) $g->grade_point,
                'mark_from' => (float) $g->mark_from,
                'mark_to' => (float) $g->mark_to,
            ]);

        return ApiResponse::success($scale, 'Grading scale retrieved.');
    }

    /** Search individual student result by roll, year, class_config, exam & optional section. */
    public function search(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $enrollments = $this->matchingEnrollments($data, [
            'student', 'classConfig.schoolClass', 'classConfig.section', 'classConfig.shift', 'group',
        ]);

        if ($enrollments->isEmpty()) {
            return ApiResponse::error('No student found matching the provided Roll / Student ID in this class and year.', 'NOT_FOUND', 404);
        }

        // A section-wide search can match the same roll in several class configs; take the
        // first enrollment that actually has processed results for the selected exam.
        $matched = null;
        $summary = null;
        $subjectResults = new Collection;

        foreach ($enrollments as $enrollment) {
            $summary = StudentExamSummary::where('student_id', $enrollment->student_id)
                ->where('class_config_id', $enrollment->class_config_id)
                ->where('exam_id', $data['exam_id'])
                ->first();

            $subjectResults = StudentExamResult::with(['subject:id,name,name_bn,code,serial', 'grade:id,name'])
                ->where('student_id', $enrollment->student_id)
                ->where('class_config_id', $enrollment->class_config_id)
                ->where('exam_id', $data['exam_id'])
                ->get()
                ->sortBy(fn ($r) => [$r->subject?->serial ?? 0, $r->subject?->name ?? ''])
                ->values();

            // Match on results existing at all — the student may have passed or failed.
            if ($summary || $subjectResults->isNotEmpty()) {
                $matched = $enrollment;
                break;
            }
        }

        if (! $matched) {
            return ApiResponse::error('Results for the selected exam have not been published or processed yet.', 'NOT_FOUND', 404);
        }

        $exam = Exam::find($data['exam_id']);
        $year = AcademicYear::find($data['academic_year_id']);
        $gpa = $summary ? (float) $summary->gpa : null;

        return ApiResponse::success([
            'school' => $this->schoolPayload($this->publicBranchId()),
            'student' => [
                'id' => $matched->student->id,
                'name' => $matched->student->name,
                'name_bn' => $matched->student->name_bn,
                'student_uid' => $matched->student->student_uid,
                'roll_no' => $matched->roll,
                'class_name' => $matched->classConfig?->schoolClass?->name,
                'class_name_bn' => $matched->classConfig?->schoolClass?->name_bn,
                'section_name' => $matched->classConfig?->section?->name,
                'shift_name' => $matched->classConfig?->shift?->name,
                'group_name' => $matched->group?->name,
                'fathers_name' => $matched->student->fathers_name,
                'mothers_name' => $matched->student->mothers_name,
            ],
            'exam' => [
                'id' => $exam?->id,
                'name' => $exam?->name,
            ],
            'academic_year' => [
                'id' => $year?->id,
                'name' => $year?->name,
            ],
            'summary' => $summary ? [
                'total_marks' => (float) $summary->total_marks,
                'total_obtained' => (float) $summary->total_obtained,
                'gpa' => $gpa,
                // Letter grade for the overall GPA, resolved against this class's grade scale
                // instead of a hard-coded ladder on the frontend.
                'grade' => $this->letterGradeForGpa($matched->classConfig?->class_id, $gpa),
                'is_pass' => (bool) $summary->is_pass,
                'failed_subjects_count' => (int) $summary->failed_subjects_count,
                'class_position' => $summary->class_position,
                'section_position' => $summary->section_position,
            ] : null,
            'subjects' => $subjectResults->map(fn ($r) => [
                'subject_name' => $r->subject?->name,
                'subject_name_bn' => $r->subject?->name_bn,
                'subject_code' => $r->subject?->code,
                'total_marks' => (float) $r->total_marks,
                'obtained_marks' => (float) $r->obtained_marks,
                'grade' => $r->grade?->name,
                'grade_point' => (float) $r->grade_point,
                'is_pass' => (bool) $r->is_pass,
                'is_absent' => (bool) $r->is_absent,
            ])->values(),
        ], 'Student result retrieved successfully.');
    }

    /** Download individual student marksheet PDF. */
    public function downloadPdf(Request $request): Response|JsonResponse
    {
        $data = $this->validated($request);

        $enrollments = $this->matchingEnrollments($data);

        if ($enrollments->isEmpty()) {
            return ApiResponse::error('No student found matching the provided details.', 'NOT_FOUND', 404);
        }

        $definition = app(MarksheetReport::class);
        $reportData = null;
        $studentRows = null;

        foreach ($enrollments as $enrollment) {
            $candidate = $definition->data([
                'class_config_id' => $enrollment->class_config_id,
                'exam_id' => $data['exam_id'],
            ]);

            $rows = collect($candidate['rows'] ?? [])
                ->filter(fn ($r) => $r['student_id'] === $enrollment->student_id)
                ->values();

            if ($rows->isNotEmpty()) {
                $reportData = $candidate;
                $studentRows = $rows;
                break;
            }
        }

        if ($studentRows === null) {
            return ApiResponse::error('Marksheet for the selected student has not been generated or processed.', 'NOT_FOUND', 404);
        }

        $singleStudentData = [
            'rows' => $studentRows,
            'branch' => $reportData['branch'] ?? null,
        ];

        $pdf = Pdf::loadView($definition->pdfView(), [
            'title' => 'Marksheet - '.($studentRows[0]['name'] ?? ''),
            'data' => $singleStudentData,
        ]);

        return $pdf->stream('marksheet-'.$this->normalizeDigits($data['roll_no']).'.pdf');
    }

    /** Shared validation for search + PDF download. */
    private function validated(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'class_config_id' => ['nullable', 'integer'],
            'section_id' => ['required_without:class_config_id', 'nullable', 'integer'],
            'exam_id' => ['required', 'integer'],
            'roll_no' => ['required', 'string'],
        ]);
    }

    /**
     * Enrollments matching the roll number (or student UID), newest first. Branch
     * isolation comes from the `BelongsToBranch` global scope.
     *
     * @return Collection<int, Enrollment>
     */
    private function matchingEnrollments(array $data, array $with = []): Collection
    {
        $needle = $this->normalizeDigits(trim($data['roll_no']));

        $query = Enrollment::with($with)
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('is_current', true)
            ->where(function ($q) use ($needle) {
                // Rolls are stored zero-padded in some branches and bare in others; the
                // student UID is accepted too since the form labels the field "Roll / Student ID".
                $q->where('roll', $needle)
                    ->orWhere('roll', ltrim($needle, '0'))
                    ->orWhereHas('student', fn ($s) => $s->where('student_uid', $needle));
            });

        if (! empty($data['class_config_id'])) {
            $query->where('class_config_id', $data['class_config_id']);
        } elseif (! empty($data['section_id'])) {
            $query->whereIn('class_config_id', ClassConfig::where('section_id', $data['section_id'])->pluck('id'));
        }

        return $query->orderByDesc('id')->get();
    }

    /** Public-facing identity of the school, so the marksheet header isn't hard-coded client-side. */
    private function schoolPayload(int $branchId): ?array
    {
        $branch = Branch::find($branchId);

        if (! $branch) {
            return null;
        }

        return [
            'name' => $branch->name,
            'name_bn' => $branch->name_bn,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'email' => $branch->email,
            'logo_url' => $branch->logo_path ? asset('storage/'.$branch->logo_path) : null,
        ];
    }

    /** Highest grade on the class's scale whose grade point the GPA reaches. */
    private function letterGradeForGpa(?int $classId, ?float $gpa): ?string
    {
        if ($gpa === null || $classId === null) {
            return null;
        }

        return Grade::where('class_id', $classId)
            ->where('status', true)
            ->where('grade_point', '<=', $gpa)
            ->orderByDesc('grade_point')
            ->value('name');
    }

    private function normalizeDigits(string $value): string
    {
        return strtr($value, self::DIGIT_MAP);
    }
}
