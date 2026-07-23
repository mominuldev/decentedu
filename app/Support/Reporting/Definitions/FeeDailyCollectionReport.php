<?php

namespace App\Support\Reporting\Definitions;

use App\Models\Fees\FeeCollection;
use App\Support\Reporting\ReportDefinition;

/** Collections in a date range, grouped by fee head. */
class FeeDailyCollectionReport extends ReportDefinition
{
    public function key(): string
    {
        return 'fee-daily-collection';
    }

    public function title(): string
    {
        return 'Daily Collection Report';
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ];
    }

    public function data(array $params): array
    {
        $collections = FeeCollection::with('items.studentFee.feeSubHead.feeHead')
            ->whereDate('collected_at', '>=', $params['from'])
            ->whereDate('collected_at', '<=', $params['to'])
            ->get();

        $byHead = [];
        foreach ($collections as $collection) {
            foreach ($collection->items as $item) {
                $head = $item->studentFee->feeSubHead->feeHead?->name ?? 'Unknown';
                $byHead[$head] = ($byHead[$head] ?? 0) + (float) $item->amount;
            }
        }

        return [
            'from' => $params['from'],
            'to' => $params['to'],
            'total_collected' => round((float) $collections->sum('total_amount'), 2),
            'receipts_count' => $collections->count(),
            'by_head' => collect($byHead)->map(fn ($amount, $head) => ['fee_head_name' => $head, 'amount' => round($amount, 2)])->values(),
            'branch' => $this->branch(),
        ];
    }

    public function pdfView(): ?string
    {
        return 'reports.fees.daily-collection';
    }

    public function excelHeadings(): ?array
    {
        return ['Fee Head', 'Amount'];
    }

    public function excelRows(array $data): array
    {
        return $data['by_head']->map(fn (array $r) => [$r['fee_head_name'], $r['amount']])->all();
    }
}
