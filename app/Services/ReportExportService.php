<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

// PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;

class ReportExportService
{
    public function __construct(private ViewFactory $view) {}

    /**
     * Gera PDF a partir de uma view Blade genérica de tabela.
     * $opts: ['landscape' => bool, 'paper' => 'A4'|'A3'...]
     */
    public function pdf(string $view, array $data, string $filename = 'relatorio.pdf', array $opts = []): Response
    {
        $html = $this->view->make($view, $data)->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans'); // UTF-8

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($opts['paper'] ?? 'A4', !empty($opts['landscape']) ? 'landscape' : 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Gera XLSX (ou CSV, se filename terminar com .csv) a partir de um array de linhas.
     * $rows: array de arrays associativos, onde as chaves da 1ª linha viram o cabeçalho.
     */
    public function excel(array $rows, string $filename = 'relatorio.xlsx'): StreamedResponse
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'xlsx';

        return response()->streamDownload(function () use ($rows, $ext) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            if (empty($rows)) {
                $rows = [[]];
            }

            // Cabeçalhos a partir das chaves da primeira linha
            $headers = array_keys((array) reset($rows));
            $col = 1;
            foreach ($headers as $h) {
                $sheet->setCellValueByColumnAndRow($col, 1, $h);
                // Autosize nas colunas (ok para quantidades moderadas)
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
                $col++;
            }
            // Negrito no cabeçalho
            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);

            // Linhas
            $r = 2;
            foreach ($rows as $row) {
                $c = 1;
                foreach ($headers as $key) {
                    $val = $row[$key] ?? '';
                    // Força string para não perder formatação (R$ 1.234,56 etc)
                    $sheet->setCellValueExplicitByColumnAndRow($c, $r, (string) $val, DataType::TYPE_STRING);
                    $c++;
                }
                $r++;
            }

            if ($ext === 'csv') {
                $writer = new CsvWriter($spreadsheet);
                $writer->setDelimiter(';');
                $writer->setEnclosure('"');
                $writer->setSheetIndex(0);
                $writer->save('php://output');
            } else {
                $writer = new XlsxWriter($spreadsheet);
                $writer->save('php://output');
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Content-Type'  => $ext === 'csv'
                ? 'text/csv'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
