<?php

namespace App\Http\Controllers\Api\Reporting;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportArtifact;
use App\Models\Reporting\ReportArtifact;
use App\Support\ApiResponse;
use App\Support\BranchContext;
use App\Support\Reporting\GenericArrayExport;
use App\Support\Reporting\ReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generic entry point for every report definition (see ReportRegistry) — small/interactive
 * reports render inline; reports flagged queued() go through GenerateReportArtifact instead.
 */
class ReportController extends Controller
{
    public function pdf(Request $request, string $report): Response|JsonResponse
    {
        $definition = ReportRegistry::resolve($report);
        abort_if($definition->pdfView() === null, 422, 'This report has no PDF output.');
        $params = $request->validate($definition->rules());

        if ($definition->queued()) {
            return ApiResponse::success($this->queue($request, $report, 'pdf', $params), 'Report queued.', [], 202);
        }

        $data = $definition->data($params);
        $pdf = Pdf::loadView($definition->pdfView(), ['title' => $definition->title(), 'data' => $data]);

        return $pdf->stream($definition->key().'.pdf');
    }

    public function excel(Request $request, string $report): Response|JsonResponse
    {
        $definition = ReportRegistry::resolve($report);
        abort_if($definition->excelHeadings() === null, 422, 'This report has no Excel output.');
        $params = $request->validate($definition->rules());

        if ($definition->queued()) {
            return ApiResponse::success($this->queue($request, $report, 'excel', $params), 'Report queued.', [], 202);
        }

        $data = $definition->data($params);
        $export = new GenericArrayExport($definition->excelRows($data), $definition->excelHeadings());

        return Excel::download($export, $definition->key().'.xlsx');
    }

    private function queue(Request $request, string $report, string $format, array $params): array
    {
        $branchId = app(BranchContext::class)->idOrFail();

        $artifact = ReportArtifact::create([
            'branch_id' => $branchId,
            'report_key' => $report,
            'format' => $format,
            'params' => $params,
            'status' => 'pending',
            'requested_by' => $request->user()?->id,
        ]);

        GenerateReportArtifact::dispatch($artifact->id, $branchId);

        return ['artifact_id' => $artifact->id, 'status' => $artifact->refresh()->status];
    }

    public function artifactStatus(int $id): JsonResponse
    {
        $artifact = ReportArtifact::findOrFail($id);

        return ApiResponse::success([
            'id' => $artifact->id,
            'report_key' => $artifact->report_key,
            'format' => $artifact->format,
            'status' => $artifact->status,
            'error_message' => $artifact->error_message,
            'download_url' => $artifact->status === 'ready' ? route('reports.artifacts.download', $artifact->id) : null,
        ], 'Artifact status retrieved.');
    }

    public function download(int $id): StreamedResponse
    {
        $artifact = ReportArtifact::findOrFail($id);
        abort_unless($artifact->status === 'ready', 409, 'Report is not ready yet.');
        abort_unless($artifact->file_path && Storage::exists($artifact->file_path), 404, 'Report file missing.');

        $extension = $artifact->format === 'pdf' ? 'pdf' : 'xlsx';

        return Storage::download($artifact->file_path, "{$artifact->report_key}.{$extension}");
    }
}
