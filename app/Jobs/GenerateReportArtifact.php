<?php

namespace App\Jobs;

use App\Models\Reporting\ReportArtifact;
use App\Support\BranchContext;
use App\Support\Reporting\GenericArrayExport;
use App\Support\Reporting\ReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/** Large/batch report path: render off-request, store the artifact, let the UI poll+download. */
class GenerateReportArtifact implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $artifactId, private readonly int $branchId) {}

    public function handle(): void
    {
        app(BranchContext::class)->set($this->branchId);

        $artifact = ReportArtifact::findOrFail($this->artifactId);
        $artifact->update(['status' => 'processing']);

        try {
            $definition = ReportRegistry::resolve($artifact->report_key);
            $data = $definition->data($artifact->params ?? []);
            $path = "reports/{$this->branchId}/{$artifact->report_key}-{$artifact->id}.".($artifact->format === 'pdf' ? 'pdf' : 'xlsx');

            if ($artifact->format === 'pdf') {
                Storage::put($path, Pdf::loadView($definition->pdfView(), ['title' => $definition->title(), 'data' => $data])->output());
            } else {
                Excel::store(new GenericArrayExport($definition->excelRows($data), $definition->excelHeadings() ?? []), $path);
            }

            $artifact->update(['status' => 'ready', 'file_path' => $path, 'completed_at' => now()]);
        } catch (\Throwable $e) {
            $artifact->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }
}
