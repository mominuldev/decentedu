<?php

namespace App\Http\Controllers\Api\Fees;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\Reporting\Definitions\FeeDailyCollectionReport;
use App\Support\Reporting\Definitions\FeeDuesSummaryReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeReportController extends Controller
{
    public function show(Request $request, string $type): JsonResponse
    {
        return match ($type) {
            'daily-collection' => $this->dailyCollection($request),
            'dues-summary' => $this->duesSummary($request),
            default => abort(404, 'Unknown report.'),
        };
    }

    private function dailyCollection(Request $request): JsonResponse
    {
        $definition = app(FeeDailyCollectionReport::class);
        $data = $definition->data($request->validate($definition->rules()));

        return ApiResponse::success([
            'from' => $data['from'],
            'to' => $data['to'],
            'total_collected' => $data['total_collected'],
            'receipts_count' => $data['receipts_count'],
            'by_head' => $data['by_head'],
        ], 'Daily collection report retrieved.');
    }

    private function duesSummary(Request $request): JsonResponse
    {
        $definition = app(FeeDuesSummaryReport::class);
        $data = $definition->data($request->validate($definition->rules()));

        return ApiResponse::success($data['rows'], 'Dues summary retrieved.');
    }
}
