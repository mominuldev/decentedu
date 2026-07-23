<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\LedgerAccount;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LedgerAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LedgerAccount::query()->orderBy('type')->orderBy('name');
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return ApiResponse::success($query->get()->map(fn (LedgerAccount $a) => $this->transform($a)), 'Ledger accounts retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $data = $request->validate($this->rules($branchId, null));

        $account = LedgerAccount::create($data);

        return ApiResponse::success($this->transform($account), 'Ledger account created.', status: 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $branchId = app(BranchContext::class)->idOrFail();
        $account = LedgerAccount::findOrFail($id);
        abort_if($account->is_system, 422, 'System accounts cannot be edited.');
        $data = $request->validate($this->rules($branchId, $id));

        $account->update($data);

        return ApiResponse::success($this->transform($account), 'Ledger account updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $account = LedgerAccount::findOrFail($id);
        abort_if($account->is_system, 422, 'System accounts cannot be deleted.');
        abort_if($account->entries()->exists(), 422, 'This account already has voucher entries and cannot be deleted.');
        $account->delete();

        return ApiResponse::success(null, 'Ledger account deleted.');
    }

    private function rules(int $branchId, ?int $ignoreId): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', Rule::unique('ledger_accounts', 'code')->where(fn ($q) => $q->where('branch_id', $branchId)->whereNull('deleted_at'))->ignore($ignoreId)],
            'type' => ['required', Rule::in(['asset', 'liability', 'income', 'expense', 'equity'])],
            'parent_id' => ['nullable', 'integer', Rule::exists('ledger_accounts', 'id')->where('branch_id', $branchId)],
            'opening_balance' => ['sometimes', 'numeric'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    private function transform(LedgerAccount $a): array
    {
        return [
            'id' => $a->id,
            'name' => $a->name,
            'code' => $a->code,
            'type' => $a->type,
            'parent_id' => $a->parent_id,
            'is_system' => $a->is_system,
            'opening_balance' => $a->opening_balance,
            'status' => $a->status,
        ];
    }
}
