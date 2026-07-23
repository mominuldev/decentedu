<?php

namespace App\Support\Reporting;

use App\Models\Branch;
use App\Support\BranchContext;

/**
 * One report = one query + one optional PDF template + one optional Excel shape.
 * Concrete reports (marksheet, trial balance, ...) extend this so the generic
 * ReportController/GenerateReportArtifact job never need to know report-specific logic.
 */
abstract class ReportDefinition
{
    /** Route/artifact slug, e.g. "marksheet". Must match the ReportRegistry key. */
    abstract public function key(): string;

    /** Human label for artifact listings and PDF titles. */
    abstract public function title(): string;

    /** Validation rules applied to the incoming request params. */
    abstract public function rules(): array;

    /** Fetch + shape this report's data from validated params. Read-only. */
    abstract public function data(array $params): array;

    /** Blade view name rendered to PDF, or null if this report has no PDF output. */
    public function pdfView(): ?string
    {
        return null;
    }

    /** Column headings for the Excel export, or null if this report has no Excel output. */
    public function excelHeadings(): ?array
    {
        return null;
    }

    /** Flatten data() into row arrays matching excelHeadings(). */
    public function excelRows(array $data): array
    {
        return [];
    }

    /**
     * True routes generation through the queued job + artifact-download path instead of
     * rendering synchronously. Reserve for reports that can span a whole branch/year.
     */
    public function queued(): bool
    {
        return false;
    }

    protected function branch(): ?Branch
    {
        $id = app(BranchContext::class)->id();

        return $id ? Branch::find($id) : null;
    }
}
