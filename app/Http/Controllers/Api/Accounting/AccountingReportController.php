<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\Reporting\Definitions\IncomeStatementReport;
use App\Support\Reporting\Definitions\TrialBalanceReport;
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

    private function trialBalance(Request $request): JsonResponse
    {
        $definition = app(TrialBalanceReport::class);
        $data = $definition->data($request->validate($definition->rules()));

        return ApiResponse::success([
            'rows' => $data['rows'],
            'total_debit' => $data['total_debit'],
            'total_credit' => $data['total_credit'],
        ], 'Trial balance retrieved.');
    }

    private function incomeStatement(Request $request): JsonResponse
    {
        $definition = app(IncomeStatementReport::class);
        $data = $definition->data($request->validate($definition->rules()));

        return ApiResponse::success([
            'income' => $data['income'],
            'expense' => $data['expense'],
            'total_income' => $data['total_income'],
            'total_expense' => $data['total_expense'],
            'net' => $data['net'],
        ], 'Income statement retrieved.');
    }
}
