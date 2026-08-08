<?php

declare(strict_types=1);

namespace App\Core\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * docs/ADR/ADR-022-reports-dashboard.md §b — a stateless wrapper around
 * PhpSpreadsheet, mirroring App\Core\Pdf\PdfRenderer's shape exactly: the
 * one place any Service renders tabular data to .xlsx bytes, so no module
 * couples directly to the PhpSpreadsheet API.
 */
class ExcelRenderer
{
    /**
     * One sheet, one header row, then one row per data row — no styling
     * framework, matching the PDF export's "plain HTML table" posture
     * (ADR-022 §5).
     *
     * @param list<string>      $headers
     * @param list<list<mixed>> $rows
     */
    public function render(string $sheetTitle, array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetTitle, 0, 31));

        $sheet->fromArray($headers, null, 'A1');

        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $writer = new Xlsx($spreadsheet);

        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx_');

        if ($tmpPath === false) {
            throw new \RuntimeException('Could not create a temporary file for Excel export.');
        }

        try {
            $writer->save($tmpPath);
            $bytes = file_get_contents($tmpPath);
        } finally {
            @unlink($tmpPath);
        }

        return $bytes === false ? '' : $bytes;
    }
}
