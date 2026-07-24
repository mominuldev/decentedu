<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\Examinations\ResultController;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Academic\Subject;
use App\Models\Branch;
use App\Models\Examinations\AdmitInstruction;
use App\Models\Examinations\ClassTeacherConfig;
use App\Models\Examinations\Exam;
use App\Models\Examinations\ExamConfig;
use App\Models\Examinations\ExamRoutine;
use App\Models\Examinations\Grade;
use App\Models\Examinations\Mark;
use App\Models\Examinations\MarkConfig;
use App\Models\Examinations\ShortCode;
use App\Models\Examinations\Signature;
use App\Models\Examinations\StudentFourthSubject;
use App\Models\Hr\Employee;
use App\Models\Students\Enrollment;
use App\Support\BranchContext;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ExaminationSeeder extends Seeder
{
    /**
     * Seed sample Examinations data: exam types, grade scale, short codes, exam/mark
     * configs across every class_config, then marks + general/merit processing for a
     * handful of sections per branch so the UI has ready-to-view results.
     */
    public function run(): void
    {
        $this->command->info('Seeding Examinations data...');

        $branches = Branch::all();
        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Please seed organizations first.');

            return;
        }

        foreach ($branches as $branch) {
            $this->command->info("Seeding examinations for branch: {$branch->name}");
            app(BranchContext::class)->set($branch->id);

            $academicYear = AcademicYear::where('branch_id', $branch->id)->where('is_current', true)->first()
                ?? AcademicYear::where('branch_id', $branch->id)->first();
            $classConfigs = ClassConfig::where('branch_id', $branch->id)->get();
            $subjects = Subject::where('branch_id', $branch->id)->orderBy('serial')->get();

            if (! $academicYear || $classConfigs->isEmpty() || $subjects->isEmpty()) {
                $this->command->warn("Skipping {$branch->name}: missing academic year/class configs/subjects.");

                continue;
            }

            $shortCodes = $this->createShortCodes($branch->id);
            $exams = $this->createExams($branch->id);
            $this->createGradeScale($branch->id, $classConfigs);
            $this->createExamConfigs($branch->id, $classConfigs, $exams);
            $this->createSignaturesAndInstructions($branch->id);
            $this->assignClassTeachers($branch->id, $classConfigs);

            $markConfigs = $this->createMarkConfigs($branch->id, $classConfigs, $subjects, $shortCodes, $exams);
            $this->createFourthSubjectAssignments($branch->id, $academicYear->id, $classConfigs, $subjects);

            // Full pipeline (marks -> general process -> merit process) for a sample of
            // sections per branch, so results/reports have data without an excessive seed runtime.
            $sample = $classConfigs->take(3);
            $this->seedExamRoutine($branch->id, $academicYear->id, $sample, $exams['half_yearly'], $markConfigs);
            $this->seedMarksAndProcess($branch->id, $sample, $exams, $markConfigs);

            app(BranchContext::class)->set(null);
        }

        $this->command->info('Examinations data seeded successfully.');
    }

    private function createShortCodes(int $branchId): array
    {
        $names = ['Written', 'MCQ'];
        $codes = [];
        foreach ($names as $i => $name) {
            $codes[$name] = ShortCode::firstOrCreate(
                ['branch_id' => $branchId, 'name' => $name],
                ['serial' => $i + 1, 'status' => true],
            );
        }

        return $codes;
    }

    private function createExams(int $branchId): array
    {
        $half = Exam::firstOrCreate(['branch_id' => $branchId, 'name' => 'Half Yearly Exam'], ['type' => 'monthly', 'serial' => 1, 'status' => true]);
        $annual = Exam::firstOrCreate(['branch_id' => $branchId, 'name' => 'Annual Exam'], ['type' => 'final', 'serial' => 2, 'status' => true]);
        $combined = Exam::firstOrCreate(['branch_id' => $branchId, 'name' => 'Combined Result'], ['type' => 'grand_final', 'serial' => 3, 'status' => true]);

        return ['half_yearly' => $half, 'annual' => $annual, 'combined' => $combined];
    }

    /** Standard Bangladesh-board style grade scale, per class. */
    private function createGradeScale(int $branchId, $classConfigs): void
    {
        $scale = [
            ['name' => 'A+', 'gp' => 5.00, 'from' => 80, 'to' => 100],
            ['name' => 'A', 'gp' => 4.00, 'from' => 70, 'to' => 79.99],
            ['name' => 'A-', 'gp' => 3.50, 'from' => 60, 'to' => 69.99],
            ['name' => 'B', 'gp' => 3.00, 'from' => 50, 'to' => 59.99],
            ['name' => 'C', 'gp' => 2.00, 'from' => 40, 'to' => 49.99],
            ['name' => 'D', 'gp' => 1.00, 'from' => 33, 'to' => 39.99],
            ['name' => 'F', 'gp' => 0.00, 'from' => 0, 'to' => 32.99],
        ];

        foreach ($classConfigs->pluck('class_id')->unique() as $classId) {
            foreach ($scale as $i => $g) {
                Grade::firstOrCreate(
                    ['branch_id' => $branchId, 'class_id' => $classId, 'name' => $g['name']],
                    ['grade_point' => $g['gp'], 'mark_from' => $g['from'], 'mark_to' => $g['to'], 'serial' => $i + 1, 'status' => true],
                );
            }
        }
    }

    private function createExamConfigs(int $branchId, $classConfigs, array $exams): void
    {
        foreach ($classConfigs->pluck('class_id')->unique() as $classId) {
            $config = ExamConfig::firstOrCreate(
                ['branch_id' => $branchId, 'class_id' => $classId],
                ['merit_basis' => 'grade_point', 'merit_sequential' => true],
            );
            $config->exams()->syncWithoutDetaching([$exams['half_yearly']->id, $exams['annual']->id]);
        }
    }

    private function createSignaturesAndInstructions(int $branchId): void
    {
        Signature::firstOrCreate(
            ['branch_id' => $branchId, 'position' => 'left', 'person_name' => 'Class Teacher'],
            ['designation' => 'Class Teacher', 'serial' => 1, 'status' => true],
        );
        Signature::firstOrCreate(
            ['branch_id' => $branchId, 'position' => 'right', 'person_name' => 'Head of Institution'],
            ['designation' => 'Principal', 'serial' => 2, 'status' => true],
        );

        AdmitInstruction::firstOrCreate(['branch_id' => $branchId], [
            'instruction1' => 'Bring this admit card to every exam session.',
            'instruction2' => 'Arrive at the exam hall at least 15 minutes before the start time.',
            'instruction3' => 'Mobile phones and electronic devices are not allowed in the exam hall.',
            'instruction4' => 'Any form of unfair means will lead to cancellation of the exam.',
        ]);
    }

    private function assignClassTeachers(int $branchId, $classConfigs): void
    {
        $teachers = Employee::where('branch_id', $branchId)->where('status', 'active')->get();
        if ($teachers->isEmpty()) {
            return;
        }

        foreach ($classConfigs as $i => $classConfig) {
            ClassTeacherConfig::firstOrCreate(
                ['branch_id' => $branchId, 'class_config_id' => $classConfig->id],
                ['employee_id' => $teachers[$i % $teachers->count()]->id],
            );
        }
    }

    /** Written (70) + MCQ (30) per subject, for every class_config, for both Half Yearly and Annual. */
    private function createMarkConfigs(int $branchId, $classConfigs, $subjects, array $shortCodes, array $exams): Collection
    {
        $components = [
            $shortCodes['Written']->id => ['total' => 70, 'pass' => 23],
            $shortCodes['MCQ']->id => ['total' => 30, 'pass' => 10],
        ];

        $created = collect();
        foreach ($classConfigs as $classConfig) {
            foreach ($subjects as $subject) {
                foreach ([$exams['half_yearly'], $exams['annual']] as $exam) {
                    foreach ($components as $shortCodeId => $marks) {
                        $created->push(MarkConfig::firstOrCreate(
                            [
                                'branch_id' => $branchId,
                                'class_config_id' => $classConfig->id,
                                'group_id' => null,
                                'exam_id' => $exam->id,
                                'subject_id' => $subject->id,
                                'short_code_id' => $shortCodeId,
                            ],
                            [
                                'total_marks' => $marks['total'],
                                'pass_mark' => $marks['pass'],
                                'status' => true,
                            ],
                        ));
                    }
                }
            }
        }

        return $created;
    }

    /** Assign an optional 4th subject to a sample of students in the higher classes (Nine/Ten). */
    private function createFourthSubjectAssignments(int $branchId, int $academicYearId, $classConfigs, $subjects): void
    {
        $higherConfigs = $classConfigs->filter(
            fn (ClassConfig $c) => in_array($c->schoolClass?->name, ['Nine', 'Ten'], true)
        );
        $fourthSubject = $subjects->firstWhere('name', 'ICT') ?? $subjects->last();
        if (! $fourthSubject) {
            return;
        }

        foreach ($higherConfigs as $classConfig) {
            $studentIds = Enrollment::where('class_config_id', $classConfig->id)->current()->limit(5)->pluck('student_id');
            foreach ($studentIds as $studentId) {
                StudentFourthSubject::firstOrCreate(
                    ['branch_id' => $branchId, 'student_id' => $studentId, 'academic_year_id' => $academicYearId],
                    ['class_config_id' => $classConfig->id, 'subject_id' => $fourthSubject->id],
                );
            }
        }
    }

    private function seedExamRoutine(int $branchId, int $academicYearId, $sample, Exam $exam, $markConfigs): void
    {
        $date = now()->addDays(14);
        foreach ($sample as $classConfig) {
            $subjectIds = $markConfigs->where('class_config_id', $classConfig->id)->where('exam_id', $exam->id)
                ->pluck('subject_id')->unique()->values();

            foreach ($subjectIds as $i => $subjectId) {
                ExamRoutine::firstOrCreate(
                    ['branch_id' => $branchId, 'class_config_id' => $classConfig->id, 'exam_id' => $exam->id, 'subject_id' => $subjectId, 'group_id' => null],
                    [
                        'academic_year_id' => $academicYearId,
                        'exam_date' => $date->copy()->addDays($i)->toDateString(),
                        'start_time' => '10:00',
                        'end_time' => '13:00',
                        'room_no' => 'Room '.(($i % 3) + 1),
                        'exam_session' => 'Morning',
                    ],
                );
            }
        }
    }

    private function seedMarksAndProcess(int $branchId, $sample, array $exams, $markConfigs): void
    {
        $halfYearly = $exams['half_yearly'];

        foreach ($sample as $classConfig) {
            $students = Enrollment::where('class_config_id', $classConfig->id)->current()->get();
            $configs = $markConfigs->where('class_config_id', $classConfig->id)->where('exam_id', $halfYearly->id)->groupBy('subject_id');

            if ($students->isEmpty() || $configs->isEmpty()) {
                continue;
            }

            foreach ($students as $enrollment) {
                // ~10% absent across the board; otherwise a realistic mark spread (mostly passing).
                $studentIsWeak = mt_rand(1, 100) <= 15;

                foreach ($configs as $subjectId => $subjectConfigs) {
                    $isAbsent = mt_rand(1, 100) <= 5;

                    foreach ($subjectConfigs as $config) {
                        $ratio = $isAbsent ? 0 : ($studentIsWeak ? mt_rand(20, 45) : mt_rand(55, 98)) / 100;
                        Mark::updateOrCreate(
                            ['student_id' => $enrollment->student_id, 'mark_config_id' => $config->id],
                            [
                                'branch_id' => $branchId,
                                'enrollment_id' => $enrollment->id,
                                'exam_id' => $halfYearly->id,
                                'obtained' => $isAbsent ? null : round($config->total_marks * $ratio, 2),
                                'is_absent' => $isAbsent,
                            ],
                        );
                    }
                }
            }
        }

        $this->command->info('Running general + merit process for the seeded sample...');
        $resultController = app(ResultController::class);

        foreach ($sample as $classConfig) {
            $resultController->generalProcess(new Request([
                'class_config_id' => $classConfig->id,
                'exam_id' => $halfYearly->id,
            ]));
        }

        foreach ($sample->pluck('class_id')->unique() as $classId) {
            $resultController->meritProcess(new Request([
                'class_id' => $classId,
                'exam_id' => $halfYearly->id,
            ]));
        }
    }
}
