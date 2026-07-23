<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\LedgerAccount;
use App\Models\Accounting\VoucherEntry;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingReportController extends Controller
{
    public function show(Request $request, string $type): JsonResponse
    {
        return match ($type) {
            'trial-balance' => $this->trialBalance($request),
            'income-statement' => $this->incomeStatement($request),
            default => abort(404, 'Unknown report.'),
        };
    }

    /** Debit/credit totals per ledger account (incl. opening balance), within an optional date range. */
    private function trialBalance(Request $request): JsonResponse
    {
        app(BranchContext::class)->idOrFail();
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $accounts = LedgerAccount::where('status', true)->orderBy('type')->orderBy('name')->get();
        $entryTotals = VoucherEntry::query()
            ->selectRaw('ledger_account_id, SUM(debit) as debit, SUM(credit) as credit')
            ->whereIn('ledger_account_id', $accounts->pluck('id'))
            ->when(! empty($data['from']) || ! empty($data['to']), function ($q) use ($data) {
                $q->whereHas('voucher', function ($v) use ($data) {
                    if (! empty($data['from'])) {
                        $v->whereDate('date', '>=', $data['from']);
                    }
                    if (! empty($data['to'])) {
                        $v->whereDate('date', '<=', $data['to']);
                    }
                });
            })
            ->groupBy('ledger_account_id')
            ->get()
            ->keyBy('ledger_account_id');

        $rows = $accounts->map(function (LedgerAccount $a) use ($entryTotals) {
            $totals = $entryTotals->get($a->id);
            $debit = (float) ($totals->debit ?? 0);
            $credit = (float) ($totals->credit ?? 0);
            $opening = (float) $a->opening_balance;
            $isDebitNature = in_array($a->type, ['asset', 'expense'], true);
            $balance = $isDebitNature ? $opening + $debit - $credit : $opening + $credit - $debit;

            return [
                'ledger_account_id' => $a->id,
                'name' => $a->name,
                'code' => $a->code,
                'type' => $a->type,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'balance' => round($balance, 2),
                'balance_side' => $isDebitNature ? 'debit' : 'credit',
            ];
        });

        return ApiResponse::success([
            'rows' => $rows,
            'total_debit' => round($rows->sum('debit'), 2),
            'total_credit' => round($rows->sum('credit'), 2),
        ], 'Trial balance retrieved.');
    }

    /** Total income minus total expense, within an optional date range. */
    private function incomeStatement(Request $request): JsonResponse
    {
        app(BranchContext::class)->idOrFail();
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $accounts = LedgerAccount::whereIn('type', ['income', 'expense'])->where('status', true)->get();
        $entryTotals = VoucherEntry::query()
            ->selectRaw('ledger_account_id, SUM(debit) as debit, SUM(credit) as credit')
            ->whereIn('ledger_account_id', $accounts->pluck('id'))
            ->when(! empty($data['from']) || ! empty($data['to']), function ($q) use ($data) {
                $q->whereHas('voucher', function ($v) use ($data) {
                    if (! empty($data['from'])) {
                        $v->whereDate('date', '>=', $data['from']);
                    }
                    if (! empty($data['to'])) {
                        $v->whereDate('date', '<=', $data['to']);
                    }
                });
            })
            ->groupBy('ledger_account_id')
            ->get()
            ->keyBy('ledger_account_id');

        $income = collect();
        $expense = collect();
        foreach ($accounts as $a) {
            $totals = $entryTotals->get($a->id);
            $debit = (float) ($totals->debit ?? 0);
            $credit = (float) ($totals->credit ?? 0);
            $row = ['name' => $a->name, 'code' => $a->code, 'amount' => round($a->type === 'income' ? $credit - $debit : $debit - $credit, 2)];
            $a->type === 'income' ? $income->push($row) : $expense->push($row);
        }

        $totalIncome = round($income->sum('amount'), 2);
        $totalExpense = round($expense->sum('amount'), 2);

        return ApiResponse::success([
            'income' => $income->values(),
            'expense' => $expense->values(),
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net' => round($totalIncome - $totalExpense, 2),
        ], 'Income statement retrieved.');
    }
}
