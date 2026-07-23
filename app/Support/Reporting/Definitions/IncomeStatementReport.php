<?php

namespace App\Support\Reporting\Definitions;

use App\Models\Accounting\LedgerAccount;
use App\Models\Accounting\VoucherEntry;
use App\Support\BranchContext;
use App\Support\Reporting\ReportDefinition;

/** Total income minus total expense, within an optional date range. */
class IncomeStatementReport extends ReportDefinition
{
    public function key(): string
    {
        return 'income-statement';
    }

    public function title(): string
    {
        return 'Income Statement';
    }

    public function rules(): array
    {
        return ['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']];
    }

    public function data(array $params): array
    {
        app(BranchContext::class)->idOrFail();

        $accounts = LedgerAccount::whereIn('type', ['income', 'expense'])->where('status', true)->get();
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

        return [
            'income' => $income->values(),
            'expense' => $expense->values(),
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net' => round($totalIncome - $totalExpense, 2),
            'branch' => $this->branch(),
        ];
    }

    public function pdfView(): ?string
    {
        return 'reports.accounting.income-statement';
    }

    public function excelHeadings(): ?array
    {
        return ['Section', 'Code', 'Account', 'Amount'];
    }

    public function excelRows(array $data): array
    {
        $rows = $data['income']->map(fn (array $r) => ['Income', $r['code'], $r['name'], $r['amount']])->all();

        return array_merge($rows, $data['expense']->map(fn (array $r) => ['Expense', $r['code'], $r['name'], $r['amount']])->all());
    }
}
