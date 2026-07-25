<?php

namespace App\Support\Reporting\Definitions;

use App\Models\Students\Student;
use App\Support\BranchContext;
use App\Support\Reporting\ReportDefinition;
use Illuminate\Validation\Rule;

class StudentListReport extends ReportDefinition
{
    public function key(): string
    {
        return 'student-list';
    }

    public function title(): string
    {
        return 'Student List';
    }

    public function rules(): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        return [
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,transferred,left,passed_out'],
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
            'class_config_id' => ['nullable', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'class_id' => ['nullable', 'integer', Rule::exists('classes', 'id')->where('branch_id', $branchId)],
            'section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')->where('branch_id', $branchId)],
            'roll' => ['nullable', 'string'],
        ];
    }

    /** Mirrors StudentController::index()'s filter logic (see docs there for the year/current-enrollment rationale). */
    public function data(array $params): array
    {
        $query = Student::query()->with([
            'currentEnrollment.classConfig.schoolClass',
            'currentEnrollment.classConfig.section',
            'currentEnrollment.classConfig.shift',
        ]);

        if (! empty($params['search'])) {
            $query->search($params['search']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $hasYear = ! empty($params['academic_year_id']);
        $enrollmentRelation = $hasYear ? 'enrollments' : 'currentEnrollment';

        if ($hasYear || ! empty($params['class_config_id']) || ! empty($params['class_id']) || ! empty($params['section_id']) || ! empty($params['roll'])) {
            $query->whereHas($enrollmentRelation, function ($q) use ($params, $hasYear) {
                if ($hasYear) {
                    $q->where('academic_year_id', $params['academic_year_id']);
                }

                if (! empty($params['class_config_id'])) {
                    $q->where('class_config_id', $params['class_config_id']);
                }

                if (! empty($params['roll'])) {
                    $q->where('roll', $params['roll']);
                }

                if (! empty($params['class_id']) || ! empty($params['section_id'])) {
                    $q->whereHas('classConfig', function ($cq) use ($params) {
                        if (! empty($params['class_id'])) {
                            $cq->where('class_id', $params['class_id']);
                        }

                        if (! empty($params['section_id'])) {
                            $cq->where('section_id', $params['section_id']);
                        }
                    });
                }
            });
        }

        $students = $query->orderBy('name')->limit(1000)->get();

        return ['rows' => $students, 'branch' => $this->branch()];
    }

    public function pdfView(): ?string
    {
        return 'reports.students.list';
    }

    public function excelHeadings(): ?array
    {
        return ['UID', 'Name', 'Class', 'Roll', "Father's Name", 'Mobile', 'Status'];
    }

    public function excelRows(array $data): array
    {
        return $data['rows']->map(fn (Student $s) => [
            $s->student_uid,
            $s->name,
            $s->currentEnrollment?->classConfig?->label() ?? '',
            $s->currentEnrollment?->roll ?? '',
            $s->fathers_name,
            $s->mobile ?? '',
            $s->status,
        ])->all();
    }
}
