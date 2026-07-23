<?php

namespace App\Listeners;

use App\Events\FeeCollected;
use App\Models\Accounting\LedgerAccount;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherEntry;
use Illuminate\Support\Facades\DB;

/**
 * Fees stays ignorant of Accounting internals (docs/03 §2.2): this listener posts a "receive"
 * voucher per collection — Debit Cash/Bank, Credit each fee head's income account (+ a shared
 * Fine & Penalty Income account for any fine portion) — against a fixed, auto-provisioned chart
 * of accounts (no admin mapping UI, per the approved v1 scope).
 */
class PostFeeCollectionToLedger
{
    public function handle(FeeCollected $event): void
    {
        $collection = $event->collection;
        $branchId = $collection->branch_id;

        $items = $collection->items()->with('studentFee.feeSubHead.feeHead')->get();
        if ($items->isEmpty()) {
            return;
        }

        $debitAccount = $collection->payment_method === 'cash'
            ? LedgerAccount::systemAccount($branchId, 'CASH', 'Cash in Hand', 'asset')
            : LedgerAccount::systemAccount($branchId, 'BANK', 'Bank Account', 'asset');

        $fineIncomeAccount = LedgerAccount::systemAccount($branchId, 'FINE-INC', 'Fine & Penalty Income', 'income');

        $creditsByAccount = []; // ledger_account_id => amount
        $addCredit = function (int $ledgerAccountId, float $amount) use (&$creditsByAccount): void {
            if ($amount <= 0) {
                return;
            }
            $creditsByAccount[$ledgerAccountId] = ($creditsByAccount[$ledgerAccountId] ?? 0) + $amount;
        };

        foreach ($items as $item) {
            $head = $item->studentFee->feeSubHead->feeHead;
            $base = (float) $item->amount - (float) $item->fine_paid;

            if ($base > 0) {
                $incomeAccount = LedgerAccount::systemAccount($branchId, 'INC-'.$head->id, 'Fee Income - '.$head->name, 'income');
                $addCredit($incomeAccount->id, $base);
            }
            $addCredit($fineIncomeAccount->id, (float) $item->fine_paid);
        }

        $total = round((float) $collection->total_amount, 2);
        $creditTotal = round(array_sum($creditsByAccount), 2);
        abort_unless(abs($creditTotal - $total) < 0.01, 500, 'Fee collection posting is unbalanced — debit/credit mismatch.');

        DB::transaction(function () use ($collection, $branchId, $debitAccount, $creditsByAccount, $total) {
            $voucher = Voucher::create([
                'branch_id' => $branchId,
                'type' => 'receive',
                'voucher_no' => Voucher::nextNumber($branchId, 'receive'),
                'date' => $collection->collected_at->toDateString(),
                'note' => 'Fee collection receipt '.$collection->receipt_no,
                'total' => $total,
                'created_by' => $collection->collected_by,
            ]);

            VoucherEntry::create(['voucher_id' => $voucher->id, 'ledger_account_id' => $debitAccount->id, 'debit' => $total, 'credit' => 0]);
            foreach ($creditsByAccount as $ledgerAccountId => $amount) {
                VoucherEntry::create(['voucher_id' => $voucher->id, 'ledger_account_id' => $ledgerAccountId, 'debit' => 0, 'credit' => $amount]);
            }

            $collection->update(['voucher_id' => $voucher->id]);
        });
    }
}
