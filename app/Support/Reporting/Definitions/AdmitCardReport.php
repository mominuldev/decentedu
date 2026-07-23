<?php

namespace App\Support\Reporting\Definitions;

use App\Models\Examinations\AdmitInstruction;
use App\Models\Examinations\ExamRoutine;
use App\Models\Examinations\Signature;
use App\Models\Students\Enrollment;
use App\Support\BranchContext;
use App\Support\Reporting\Definitions\Concerns\BuildsExamRoster;
use App\Support\Reporting\ReportDefinition;
use Illuminate\Validation\Rule;

class AdmitCardReport extends ReportDefinition
{
    use BuildsExamRoster;

    public function key(): string
    {
        return 'admit-card';
    }

    public function title(): string
    {
        return 'Admit Card';
    }

    public function rules(): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        return [
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('branch_id', $branchId)],
            'group_id' => ['nullable', 'integer'],
        ];
    }

    public function data(array $params): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $students = $this->roster($params['class_config_id'], $params['group_id'] ?? null)->map(fn (Enrollment $e) => [
            'student_id' => $e->student_id,
            'roll' => $e->roll,
            'name' => $e->student?->name,
            'photo_path' => $e->student?->photo_path,
        ]);

        $routine = ExamRoutine::with('subject')
            ->where('class_config_id', $params['class_config_id'])
            ->where('exam_id', $params['exam_id'])
            ->orderBy('exam_date')->orderBy('start_time')
            ->get()
            ->map(fn (ExamRoutine $r) => [
                'subject_name' => $r->subject?->name,
                'exam_date' => $r->exam_date?->toDateString(),
                'start_time' => $r->start_time,
                'end_time' => $r->end_time,
                'room_no' => $r->room_no,
            ]);

        return [
            'students' => $students,
            'routine' => $routine,
            'instructions' => AdmitInstruction::where('branch_id', $branchId)->first(),
            'signatures' => Signature::where('status', true)->orderBy('position')->get(['position', 'person_name', 'designation']),
            'branch' => $this->branch(),
        ];
    }

    public function pdfView(): ?string
    {
        return 'reports.examinations.admit-card';
    }
}
