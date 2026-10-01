<?php

namespace App\Reports;

use App\Enums\AuditAction;
use App\Services\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta cualquier Report a Excel, CSV o PDF.
 */
class ReportExporter
{
    public const array FORMATS = ['xlsx', 'csv', 'pdf'];

    /** Límite de renglones en PDF para no agotar memoria con dompdf. */
    public const int PDF_MAX_ROWS = 5000;

    public function __construct(private readonly AuditLogger $audit) {}

    public function export(Report $report, string $format): Response
    {
        $this->audit->log(AuditAction::Export, 'Reportes', null, null, [
            'reporte' => $report->key(),
            'formato' => $format,
            'filtros' => array_filter($report->filters(), fn ($v) => $v !== null && $v !== ''),
        ], "Exportación {$report->title()} ({$format})");

        $filename = str($report->title())->slug()->append('-'.now()->format('Ymd-His').'.'.$format)->toString();

        return match ($format) {
            'pdf' => $this->pdf($report, $filename),
            default => $this->spreadsheet($report, $format, $filename),
        };
    }

    private function spreadsheet(Report $report, string $format, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report, $format) {
            $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValuesWithStyle(array_values($report->columns()), (new Style)->withFontBold(true)));

            $report->query()->lazy(500)->each(function (Model $model) use ($writer, $report) {
                $writer->addRow(Row::fromValues(array_values($report->row($model))));
            });

            if ($summary = $report->summary()) {
                $writer->addRow(Row::fromValues([]));
                foreach ($summary as $label => $value) {
                    $writer->addRow(Row::fromValues([$label, $value]));
                }
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function pdf(Report $report, string $filename): Response
    {
        $rows = $report->query()->limit(self::PDF_MAX_ROWS)->get()->map(fn (Model $m) => $report->row($m));

        return Pdf::loadView('pdf.report', [
            'title' => $report->title(),
            'columns' => $report->columns(),
            'rows' => $rows,
            'summary' => $report->summary(),
            'truncated' => $rows->count() >= self::PDF_MAX_ROWS,
        ])->setPaper('letter', count($report->columns()) > 7 ? 'landscape' : 'portrait')->download($filename);
    }
}
