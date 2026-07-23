<?php

namespace App\Support\Reporting\Definitions;

use App\Models\Accounting\LedgerAccount;
use App\Models\Accounting\VoucherEntry;
use App\Support\BranchContext;
use App\Support\Reporting\ReportDefinition;

/** Debit/credit totals per ledger account (incl. opening balance), within an optional date range. */
class TrialBalanceReport extends ReportDefinition
{
    public function key(): string
    {
        return 'trial-balance';
    }

    public function title(): string
    {
        return 'Trial Balance';
    }

    public function rules(): array
    {
        return ['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']];
    }

    public function data(array $params): array
    {
        app(BranchContext::class)->idOrFail();

        $accounts = LedgerAccount::where('status', true)->orderBy('type')->orderBy('name')->get();
        $entryTotals = VoucherEntry::query()
            ->selectRaw('ledger_account_id, SUM(debit) as debit, SUM(credit) as credit')
            ->whereIn('ledger_account_id', $accounts->pluck('id'))
            ->when(! empty($params['from']) || ! empty($params['to']), function ($q) use ($params) {
                $q->whereHas('voucher', function ($v) use ($params) {
                    if (! empty($params['from'])) {
                        $v->whereDate('date', '>=', $params['from']);
                    }
                    if (! empty($params['to'])) {
                        $v->whereDate('date', '<=', $params['to']);
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

        return [
            'rows' => $rows,
            'total_debit' => round($rows->sum('debit'), 2),
            'total_credit' => round($rows->sum('credit'), 2),
            'branch' => $this->branch(),
        ];
    }

    public function pdfView(): ?string
    {
        return 'reports.accounting.trial-balance';
    }

    public function excelHeadings(): ?array
    {
        return ['Code', 'Account', 'Type', 'Debit', 'Credit', 'Balance', 'Side'];
    }

    public function excelRows(array $data): array
    {
        return $data['rows']->map(fn (array $r) => [
            $r['code'], $r['name'], $r['type'], $r['debit'], $r['credit'], $r['balance'], $r['balance_side'],
        ])->all();
    }
}
