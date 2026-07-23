<?php

namespace App\Support\Reporting;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Feeds any ReportDefinition's excelHeadings()/excelRows() into maatwebsite/excel. */
class GenericArrayExport implements FromArray, WithHeadings
{
    public function __construct(private readonly array $rows, private readonly array $headings) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
