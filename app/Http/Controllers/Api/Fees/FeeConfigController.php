<?php

namespace App\Http\Controllers\Api\Fees;

use App\Http\Controllers\Controller;
use App\Models\Fees\FeeConfig;
use App\Models\Fees\FeeSubHead;
use App\Models\Fees\FeeWaiverConfig;
use App\Models\Fees\StudentFee;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Fee structure: payable amount per class_config x fee_sub_head x academic_year.
 * "assess" turns the structure into per-student student_fees rows (applying waivers), the same
 * two-step shape as Examinations' mark_configs -> marks.
 */
class FeeConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
        ]);

        $subHeads = FeeSubHead::with('feeHead')->where('status', true)->orderBy('serial')->get();
        $existing = FeeConfig::where('class_config_id', $data['class_config_id'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->get()
            ->keyBy('fee_sub_head_id');

        $rows = $subHeads->map(fn (FeeSubHead $sh) => [
            'fee_sub_head_id' => $sh->id,
            'fee_sub_head_name' => $sh->name,
            'fee_head_name' => $sh->feeHead?->name,
            'amount' => $existing->get($sh->id)?->amount,
        ]);

        return ApiResponse::success($rows, 'Fee configuration retrieved.');
    }

    public function save(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
            'items' => ['required', 'array'],
            'items.*.fee_sub_head_id' => ['required', 'integer', Rule::exists('fee_sub_heads', 'id')->where('branch_id', $branchId)],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $saved = collect();
        DB::transaction(function () use ($data, &$saved) {
            foreach ($data['items'] as $item) {
                $saved->push(FeeConfig::updateOrCreate(
                    [
                        'class_config_id' => $data['class_config_id'],
                        'fee_sub_head_id' => $item['fee_sub_head_id'],
                        'academic_year_id' => $data['academic_year_id'],
                    ],
                    ['amount' => $item['amount'], 'updated_by' => auth()->id(), 'created_by' => auth()->id()],
                ));
            }
        });

        return ApiResponse::success($saved, 'Fee configuration saved.');
    }

    /**
     * Generate/refresh student_fees for every currently-enrolled student in a class_config, from
     * its fee_configs + any applicable fee_waiver_configs. Re-running preserves paid_amount/fine
     * already recorded — it only refreshes payable/waiver/due_date.
     */
    public function assess(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'class_config_id' => ['required', 'integer', Rule::exists('class_configs', 'id')->where('branch_id', $branchId)],
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')->where('branch_id', $branchId)],
        ]);

        $configs = FeeConfig::where('class_config_id', $data['class_config_id'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->get();
        abort_if($configs->isEmpty(), 422, 'No fee configuration found for this class — configure fee amounts first.');

        $enrollments = \App\Models\Students\Enrollment::where('class_config_id', $data['class_config_id'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->current()
            ->get();
        abort_if($enrollments->isEmpty(), 422, 'No current enrollments found for this class.');

        $timeConfigs = \App\Models\Fees\FeeTimeConfig::whereIn('fee_sub_head_id', $configs->pluck('fee_sub_head_id'))
            ->where('academic_year_id', $data['academic_year_id'])
            ->get()
            ->keyBy('fee_sub_head_id');

        $waiverConfigs = FeeWaiverConfig::with('feeWaiver')
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->where('academic_year_id', $data['academic_year_id'])
            ->get()
            ->groupBy('student_id');

        $assessed = 0;
        DB::transaction(function () use ($enrollments, $configs, $timeConfigs, $waiverConfigs, $data, &$assessed) {
            foreach ($enrollments as $enrollment) {
                $studentWaivers = $waiverConfigs->get($enrollment->student_id, collect());

                foreach ($configs as $config) {
                    $applicable = $studentWaivers->filter(
                        fn (FeeWaiverConfig $w) => $w->fee_sub_head_id === null || $w->fee_sub_head_id === $config->fee_sub_head_id
                    );
                    $waiverAmount = 0.0;
                    foreach ($applicable as $w) {
                        $waiverAmount += $w->feeWaiver->amountFor((float) $config->amount - $waiverAmount);
                    }
                    $waiverAmount = min($waiverAmount, (float) $config->amount);

                    $studentFee = StudentFee::where('student_id', $enrollment->student_id)
                        ->where('fee_sub_head_id', $config->fee_sub_head_id)
                        ->where('academic_year_id', $data['academic_year_id'])
                        ->first();

                    $timeConfig = $timeConfigs->get($config->fee_sub_head_id);

                    if ($studentFee) {
                        $studentFee->payable_amount = $config->amount;
                        $studentFee->waiver_amount = $waiverAmount;
                        $studentFee->due_date = $timeConfig?->due_date;
                        $studentFee->enrollment_id = $enrollment->id;
                        $studentFee->class_config_id = $data['class_config_id'];
                        $studentFee->save();
                    } else {
                        StudentFee::create([
                            'student_id' => $enrollment->student_id,
                            'enrollment_id' => $enrollment->id,
                            'class_config_id' => $data['class_config_id'],
                            'fee_sub_head_id' => $config->fee_sub_head_id,
                            'academic_year_id' => $data['academic_year_id'],
                            'payable_amount' => $config->amount,
                            'waiver_amount' => $waiverAmount,
                            'due_date' => $timeConfig?->due_date,
                        ]);
                    }
                    $assessed++;
                }
            }
        });

        return ApiResponse::success(['student_fees_assessed' => $assessed], 'Fees assessed.');
    }
}
