<?php

namespace App\Http\Controllers;

use App\Models\Emprestimo;
use Dompdf\Dompdf;
use Illuminate\Support\Str;

class EmprestimoReciboController extends Controller
{
    public function download(Emprestimo $emprestimo)
    {
        // credor pode vir do .env (com fallback no APP_NAME)
        $credor = [
            'nome' => config('app.recibo_credor_nome', config('app.name')),
            'cpf'  => config('app.recibo_credor_cpf'), // pode ser null
            'cidade' => config('app.recibo_cidade'),
            'uf'     => config('app.recibo_uf'),
        ];

        $cliente = $emprestimo->cliente; // assumindo relação
        $valor   = $emprestimo->valor_principal;

        $data = [
            'emprestimo' => $emprestimo,
            'cliente'    => $cliente,
            'credor'     => $credor,
            'valor'      => $valor,
            'hoje'       => now(),
        ];

        $html = view('recibos/emprestimo', $data)->render();

        $pdf = new Dompdf([
            'chroot'          => public_path(), // permite imagens locais se quiser
            'isRemoteEnabled' => true,
        ]);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = 'recibo-emprestimo-'.$emprestimo->id.'.pdf';
        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
