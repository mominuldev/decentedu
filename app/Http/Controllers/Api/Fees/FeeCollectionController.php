<?php

namespace App\Http\Controllers\Api\Fees;

use App\Events\FeeCollected;
use App\Http\Controllers\Controller;
use App\Models\Fees\FeeCollection;
use App\Models\Fees\FeeCollectionItem;
use App\Models\Fees\FeeTimeConfig;
use App\Models\Fees\StudentFee;
use App\Models\Students\Student;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeeCollectionController extends Controller
{
    /** Outstanding student_fees for one student — the dues list a collection is built from. */
    public function dues(int $studentId): JsonResponse
    {
        app(BranchContext::class)->idOrFail();
        Student::findOrFail($studentId);

        $rows = StudentFee::with(['feeSubHead.feeHead'])
            ->where('student_id', $studentId)
            ->where('status', '!=', 'paid')
            ->get()
            ->map(function (StudentFee $sf) {
                $overdue = $sf->due_date && now()->startOfDay()->gt($sf->due_date);
                $timeConfig = $overdue && $sf->fine_amount == 0
                    ? FeeTimeConfig::where('fee_sub_head_id', $sf->fee_sub_head_id)->where('academic_year_id', $sf->academic_year_id)->first()
                    : null;
                $projectedFine = $sf->fine_amount > 0 ? (float) $sf->fine_amount : (float) ($timeConfig?->fine_amount ?? 0);

                return [
                    'student_fee_id' => $sf->id,
                    'fee_head_name' => $sf->feeSubHead->feeHead?->name,
                    'fee_sub_head_name' => $sf->feeSubHead->name,
                    'payable_amount' => $sf->payable_amount,
                    'waiver_amount' => $sf->waiver_amount,
                    'fine_amount' => $sf->fine_amount,
                    'paid_amount' => $sf->paid_amount,
                    'due_date' => $sf->due_date?->toDateString(),
                    'is_overdue' => $overdue,
                    'projected_fine' => $projectedFine,
                    'due_amount' => round($sf->dueAmount() + ($sf->fine_amount == 0 ? $projectedFine : 0), 2),
                    'status' => $sf->status,
                ];
            });

        return ApiResponse::success($rows, 'Dues retrieved.');
    }

    public function index(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $query = FeeCollection::with('student')->orderByDesc('collected_at');

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('collected_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('collected_at', '<=', $request->date('to'));
        }

        $perPage = min((int) $request->query('per_page', 25), 200);
        $page = $query->paginate($perPage);

        return ApiResponse::success(
            collect($page->items())->map(fn (FeeCollection $c) => $this->transform($c)),
            'Collections retrieved.',
            ['pagination' => ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]],
        );
    }

    public function show(int $id): JsonResponse
    {
        $collection = FeeCollection::with(['student', 'items.studentFee.feeSubHead.feeHead', 'voucher'])->findOrFail($id);

        return ApiResponse::success([
            ...$this->transform($collection),
            'items' => $collection->items->map(fn (FeeCollectionItem $i) => [
                'fee_head_name' => $i->studentFee->feeSubHead->feeHead?->name,
                'fee_sub_head_name' => $i->studentFee->feeSubHead->name,
                'amount' => $i->amount,
                'fine_paid' => $i->fine_paid,
            ]),
            'voucher_no' => $collection->voucher?->voucher_no,
        ], 'Receipt retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('branch_id', $branchId)],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'mobile_banking', 'cheque'])],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.student_fee_id' => ['required', 'integer', Rule::exists('student_fees', 'id')->where('branch_id', $branchId)],
            'items.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $paidAt = $data['paid_at'] ?? now();
        $paidAt = $paidAt instanceof Carbon ? $paidAt : Carbon::parse($paidAt);

        $collection = DB::transaction(function () use ($data, $branchId, $paidAt) {
            $totalAmount = 0;
            $itemRows = [];

            foreach ($data['items'] as $item) {
                $studentFee = StudentFee::where('id', $item['student_fee_id'])->where('student_id', $data['student_id'])->lockForUpdate()->first();
                abort_if(! $studentFee, 422, 'One of the fees does not belong to this student.');

                // Flat fine, charged once, the first time a payment is made after due_date.
                if ($studentFee->due_date && $paidAt->gt($studentFee->due_date) && (float) $studentFee->fine_amount === 0.0) {
                    $timeConfig = FeeTimeConfig::where('fee_sub_head_id', $studentFee->fee_sub_head_id)
                        ->where('academic_year_id', $studentFee->academic_year_id)->first();
                    if ($timeConfig) {
                        $studentFee->fine_amount = $timeConfig->fine_amount;
                    }
                }

                $due = $studentFee->dueAmount();
                abort_if($item['amount'] > $due + 0.01, 422, "Amount exceeds the due balance for {$studentFee->feeSubHead?->name}.");

                $finePaidSoFar = (float) FeeCollectionItem::where('student_fee_id', $studentFee->id)->sum('fine_paid');
                $fineOwed = max(0, (float) $studentFee->fine_amount - $finePaidSoFar);
                $finePaidThisItem = min((float) $item['amount'], $fineOwed);

                $studentFee->paid_amount = (float) $studentFee->paid_amount + (float) $item['amount'];
                $studentFee->status = $studentFee->dueAmount() <= 0.01 ? 'paid' : ($studentFee->paid_amount > 0 ? 'partial' : 'due');
                $studentFee->save();

                $itemRows[] = ['student_fee_id' => $studentFee->id, 'amount' => $item['amount'], 'fine_paid' => $finePaidThisItem];
                $totalAmount += (float) $item['amount'];
            }

            $collection = FeeCollection::create([
                'branch_id' => $branchId,
                'student_id' => $data['student_id'],
                'receipt_no' => $this->nextReceiptNo($branchId),
                'collected_at' => $paidAt,
                'total_amount' => round($totalAmount, 2),
                'payment_method' => $data['payment_method'],
                'note' => $data['note'] ?? null,
                'collected_by' => auth()->id(),
            ]);

            foreach ($itemRows as $row) {
                $collection->items()->create($row);
            }

            return $collection;
        });

        event(new FeeCollected($collection));

        return ApiResponse::success($this->transform($collection->fresh('voucher')), 'Fee collected.', status: 201);
    }

    private function nextReceiptNo(int $branchId): string
    {
        $count = FeeCollection::withoutBranchScope()->where('branch_id', $branchId)->count();

        return sprintf('RCPT-%06d', $count + 1);
    }

    private function transform(FeeCollection $c): array
    {
        return [
            'id' => $c->id,
            'student_id' => $c->student_id,
            'student_name' => $c->student?->name,
            'receipt_no' => $c->receipt_no,
            'collected_at' => $c->collected_at?->toISOString(),
            'total_amount' => $c->total_amount,
            'payment_method' => $c->payment_method,
            'note' => $c->note,
            'voucher_id' => $c->voucher_id,
        ];
    }
}
