<?php

namespace App\Support;

use Illuminate\Http\Response;

class SharedExporter
{
    /** CSV (abre no Excel). */
    public function csv(array $rows, string $filename = 'export.csv', string $delimiter = ';'): Response
    {
        if (!str_ends_with(strtolower($filename), '.csv')) {
            $filename .= '.csv';
        }

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ];

        return response()->streamDownload(function () use ($rows, $delimiter) {
            $out = fopen('php://output', 'w');
            // BOM para o Excel interpretar UTF-8
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            if (!empty($rows)) {
                fputcsv($out, array_keys($rows[0]), $delimiter);
                foreach ($rows as $r) {
                    // garante ordem das colunas
                    $line = [];
                    foreach (array_keys($rows[0]) as $k) { $line[] = $r[$k] ?? ''; }
                    fputcsv($out, $line, $delimiter);
                }
            }
            fclose($out);
        }, $filename, $headers);
    }

    /** Alias: mantém “excel()” chamando CSV, mas com nome .csv */
    public function excel(array $rows, string $filename = 'export.csv'): Response
    {
        // se vier .xlsx, troca por .csv para o download ficar correto
        $filename = preg_replace('/\.xlsx$/i', '.csv', $filename);
        return $this->csv($rows, $filename);
    }

    /** PDF – deixa como você já estava usando (Dompdf/HTML). */
    public function pdf(string $view, array $data, string $filename = 'relatorio.pdf', array $options = [])
    {
        // Você disse que o PDF já está funcionando direitinho.
        // Se estiver usando dompdf, continue chamando daqui.
        $html = view($view, $data)->render();

        $paper = $options['paper'] ?? 'a4';
        $orient = !empty($options['landscape']) ? 'landscape' : 'portrait';

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => true,
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paper, $orient);
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
