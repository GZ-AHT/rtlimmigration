<?php

namespace App\Support\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends a table as Excel, CSV or PDF (REQ §9 "Export PDF / Excel", §9.1
 * "Export Reports"). Spreadsheets are streamed row by row, so large
 * exports do not sit in memory.
 */
class ReportExporter
{
    public const FORMATS = ['xlsx', 'csv', 'pdf'];

    /** PDFs are laid out in memory, so they are capped. */
    public const PDF_ROW_LIMIT = 2000;

    /**
     * @param  list<string>  $headings
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public function download(string $format, string $title, string $subtitle, array $headings, iterable $rows): Response
    {
        $filename = str($title)->slug()->append('-', now()->format('Y-m-d'), '.', $format)->toString();

        if ($format === 'pdf') {
            $data = [];
            $truncated = false;

            foreach ($rows as $row) {
                if (count($data) >= self::PDF_ROW_LIMIT) {
                    $truncated = true;
                    break;
                }

                $data[] = $row;
            }

            return Pdf::loadView('pdf.report', ['title' => $title, 'subtitle' => $subtitle, 'headings' => $headings, 'rows' => $data, 'truncated' => $truncated])
                ->setPaper('a4', count($headings) > 6 ? 'landscape' : 'portrait')
                ->download($filename);
        }

        return new StreamedResponse(function () use ($format, $headings, $rows) {
            $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;
            $writer->openToFile('php://output');
            $writer->addRow($format === 'csv' ? Row::fromValues($headings) : Row::fromValuesWithStyle($headings, new Style(fontBold: true)));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(self::safeCell(...), $row)));
            }

            $writer->close();
        }, 200, [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Names, notes and references are typed by users. A cell beginning with
     * = + - or @ would run as a formula when the file is opened in Excel,
     * so those are prefixed to keep them as text.
     */
    private static function safeCell(string|int|float|null $value): string|int|float
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
