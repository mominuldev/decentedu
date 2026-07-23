<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Voucher;
use App\Models\Accounting\VoucherEntry;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manual vouchers (payment/contra/journal — "receive" is normally posted automatically by
 * PostFeeCollectionToLedger, but is allowed here too for non-fee receipts). No destroy endpoint:
 * once posted, a voucher is immutable (K7 — no hard deletes on financial records); corrections
 * are made with an offsetting voucher, not an edit.
 */
class VoucherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Voucher::with('entries.ledgerAccount')->orderByDesc('date')->orderByDesc('id');
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }

        $perPage = min((int) $request->query('per_page', 25), 200);
        $page = $query->paginate($perPage);

        return ApiResponse::success(
            collect($page->items())->map(fn (Voucher $v) => $this->transform($v)),
            'Vouchers retrieved.',
            ['pagination' => ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]],
        );
    }

    public function show(int $id): JsonResponse
    {
        $voucher = Voucher::with('entries.ledgerAccount')->findOrFail($id);

        return ApiResponse::success($this->transform($voucher), 'Voucher retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate([
            'type' => ['required', Rule::in(['receive', 'payment', 'contra', 'journal'])],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*.ledger_account_id' => ['required', 'integer', Rule::exists('ledger_accounts', 'id')->where('branch_id', $branchId)],
            'entries.*.debit' => ['required', 'numeric', 'min:0'],
            'entries.*.credit' => ['required', 'numeric', 'min:0'],
        ]);

        $totalDebit = round(collect($data['entries'])->sum('debit'), 2);
        $totalCredit = round(collect($data['entries'])->sum('credit'), 2);
        if (abs($totalDebit - $totalCredit) > 0.01 || $totalDebit <= 0) {
            throw ValidationException::withMessages(['entries' => 'Total debit must equal total credit and be greater than zero.']);
        }

        $voucher = DB::transaction(function () use ($data, $branchId, $totalDebit) {
            $voucher = Voucher::create([
                'branch_id' => $branchId,
                'type' => $data['type'],
                'voucher_no' => Voucher::nextNumber($branchId, $data['type']),
                'date' => $data['date'],
                'note' => $data['note'] ?? null,
                'total' => $totalDebit,
                'created_by' => auth()->id(),
            ]);

            foreach ($data['entries'] as $entry) {
                VoucherEntry::create([
                    'voucher_id' => $voucher->id,
                    'ledger_account_id' => $entry['ledger_account_id'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                ]);
            }

            return $voucher;
        });

        return ApiResponse::success($this->transform($voucher->load('entries.ledgerAccount')), 'Voucher posted.', status: 201);
    }

    private function transform(Voucher $v): array
    {
        return [
            'id' => $v->id,
            'type' => $v->type,
            'voucher_no' => $v->voucher_no,
            'date' => $v->date?->toDateString(),
            'note' => $v->note,
            'total' => $v->total,
            'entries' => $v->entries->map(fn (VoucherEntry $e) => [
                'ledger_account_id' => $e->ledger_account_id,
                'ledger_account_name' => $e->ledgerAccount?->name,
                'debit' => $e->debit,
                'credit' => $e->credit,
            ]),
        ];
    }
}
