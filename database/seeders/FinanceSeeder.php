<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\Fees\FeeCollectionController;
use App\Http\Controllers\Api\Fees\FeeConfigController;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassConfig;
use App\Models\Branch;
use App\Models\Fees\FeeConfig;
use App\Models\Fees\FeeHead;
use App\Models\Fees\FeeSubHead;
use App\Models\Fees\FeeTimeConfig;
use App\Models\Fees\FeeWaiver;
use App\Models\Fees\FeeWaiverConfig;
use App\Models\Fees\StudentFee;
use App\Models\Students\Enrollment;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinanceSeeder extends Seeder
{
    /**
     * Seed sample Fees + Accounting data: fee heads/sub-heads, waivers, a fee structure for
     * every class_config, due-date/fine config, then assess + collect for a sample of students
     * per branch so the ledger has real vouchers to look at.
     */
    public function run(): void
    {
        $this->command->info('Seeding Finance (Fees + Accounting) data...');

        $branches = Branch::all();
        if ($branches->isEmpty()) {
            $this->command->warn('No branches found. Please seed organizations first.');

            return;
        }

        $adminUser = User::first();

        foreach ($branches as $branch) {
            $this->command->info("Seeding finance for branch: {$branch->name}");
            app(BranchContext::class)->set($branch->id);
            if ($adminUser) {
                Auth::login($adminUser);
            }

            $academicYear = AcademicYear::where('branch_id', $branch->id)->where('is_current', true)->first()
                ?? AcademicYear::where('branch_id', $branch->id)->first();
            $classConfigs = ClassConfig::where('branch_id', $branch->id)->get();

            if (! $academicYear || $classConfigs->isEmpty()) {
                $this->command->warn("Skipping {$branch->name}: missing academic year/class configs.");

                continue;
            }

            $subHeads = $this->createFeeStructureDefinitions($branch->id);
            $this->createFeeConfigs($branch->id, $classConfigs, $academicYear->id, $subHeads);
            $this->createTimeConfigs($branch->id, $academicYear->id, $subHeads);
            $this->createWaivers($branch->id);
            $this->assessAndCollect($branch->id, $classConfigs, $academicYear->id);

            app(BranchContext::class)->set(null);
            Auth::logout();
        }

        $this->command->info('Finance data seeded successfully.');
    }

    private function createFeeStructureDefinitions(int $branchId): array
    {
        $tuition = FeeHead::firstOrCreate(['branch_id' => $branchId, 'name' => 'Tuition Fee'], ['serial' => 1, 'status' => true]);
        $admission = FeeHead::firstOrCreate(['branch_id' => $branchId, 'name' => 'Admission Fee'], ['serial' => 2, 'status' => true]);
        $exam = FeeHead::firstOrCreate(['branch_id' => $branchId, 'name' => 'Examination Fee'], ['serial' => 3, 'status' => true]);

        return [
            'tuition' => FeeSubHead::firstOrCreate(['branch_id' => $branchId, 'fee_head_id' => $tuition->id, 'name' => 'Monthly Tuition'], ['serial' => 1, 'status' => true]),
            'session' => FeeSubHead::firstOrCreate(['branch_id' => $branchId, 'fee_head_id' => $admission->id, 'name' => 'Session Fee'], ['serial' => 1, 'status' => true]),
            'exam' => FeeSubHead::firstOrCreate(['branch_id' => $branchId, 'fee_head_id' => $exam->id, 'name' => 'Term Exam Fee'], ['serial' => 1, 'status' => true]),
        ];
    }

    private function createFeeConfigs(int $branchId, $classConfigs, int $academicYearId, array $subHeads): void
    {
        $amounts = ['tuition' => 800, 'session' => 1500, 'exam' => 500];
        foreach ($classConfigs as $classConfig) {
            foreach ($subHeads as $key => $subHead) {
                FeeConfig::firstOrCreate(
                    ['class_config_id' => $classConfig->id, 'fee_sub_head_id' => $subHead->id, 'academic_year_id' => $academicYearId],
                    ['branch_id' => $branchId, 'amount' => $amounts[$key]],
                );
            }
        }
    }

    private function createTimeConfigs(int $branchId, int $academicYearId, array $subHeads): void
    {
        // Tuition due date is intentionally in the past so seeded collections demonstrate the
        // flat-fine rule; the other two are due later in the term.
        FeeTimeConfig::firstOrCreate(
            ['fee_sub_head_id' => $subHeads['tuition']->id, 'academic_year_id' => $academicYearId],
            ['branch_id' => $branchId, 'due_date' => now()->subDays(15)->toDateString(), 'fine_amount' => 50],
        );
        FeeTimeConfig::firstOrCreate(
            ['fee_sub_head_id' => $subHeads['session']->id, 'academic_year_id' => $academicYearId],
            ['branch_id' => $branchId, 'due_date' => now()->addDays(30)->toDateString(), 'fine_amount' => 100],
        );
        FeeTimeConfig::firstOrCreate(
            ['fee_sub_head_id' => $subHeads['exam']->id, 'academic_year_id' => $academicYearId],
            ['branch_id' => $branchId, 'due_date' => now()->addDays(45)->toDateString(), 'fine_amount' => 25],
        );
    }

    private function createWaivers(int $branchId): void
    {
        FeeWaiver::firstOrCreate(['branch_id' => $branchId, 'name' => 'Staff Ward Discount'], ['type' => 'percentage', 'value' => 50, 'serial' => 1, 'status' => true]);
        FeeWaiver::firstOrCreate(['branch_id' => $branchId, 'name' => 'Merit Scholarship'], ['type' => 'percentage', 'value' => 25, 'serial' => 2, 'status' => true]);
    }

    private function assessAndCollect(int $branchId, $classConfigs, int $academicYearId): void
    {
        $configController = app(FeeConfigController::class);
        $collectionController = app(FeeCollectionController::class);

        $sample = $classConfigs->take(3);
        foreach ($sample as $classConfig) {
            $configController->assess(new Request([
                'class_config_id' => $classConfig->id,
                'academic_year_id' => $academicYearId,
            ]));
        }

        // One merit-scholarship waiver on a sample student, applied before this class's fees
        // were assessed above would be ideal, but assess() already ran — re-run it once more
        // after assigning the waiver so the discount is picked up.
        $firstClassConfig = $sample->first();
        if ($firstClassConfig) {
            $scholarship = FeeWaiver::where('branch_id', $branchId)->where('name', 'Merit Scholarship')->first();
            $studentId = Enrollment::where('class_config_id', $firstClassConfig->id)->current()->value('student_id');
            if ($scholarship && $studentId) {
                FeeWaiverConfig::firstOrCreate(
                    ['branch_id' => $branchId, 'student_id' => $studentId, 'fee_waiver_id' => $scholarship->id, 'fee_sub_head_id' => null, 'academic_year_id' => $academicYearId],
                );
                $configController->assess(new Request(['class_config_id' => $firstClassConfig->id, 'academic_year_id' => $academicYearId]));
            }
        }

        // Collect a realistic spread: some students fully paid, some partial, some untouched.
        foreach ($sample as $classConfig) {
            $studentFees = StudentFee::where('class_config_id', $classConfig->id)->where('academic_year_id', $academicYearId)->get()->groupBy('student_id');

            foreach ($studentFees as $studentId => $fees) {
                $roll = mt_rand(1, 100);
                if ($roll <= 40) {
                    continue; // untouched — still fully due
                }

                $items = $fees->map(function (StudentFee $fee) use ($roll) {
                    $due = $fee->dueAmount();
                    if ($due <= 0) {
                        return null;
                    }
                    $amount = $roll <= 75 ? $due : round($due * 0.5, 2); // 75%: pay in full, else half

                    return $amount > 0 ? ['student_fee_id' => $fee->id, 'amount' => $amount] : null;
                })->filter()->values();

                if ($items->isEmpty()) {
                    continue;
                }

                $methods = ['cash', 'bank', 'mobile_banking'];
                $collectionController->store(new Request([
                    'student_id' => $studentId,
                    'payment_method' => $methods[array_rand($methods)],
                    'items' => $items->toArray(),
                ]));
            }
        }
    }
}
