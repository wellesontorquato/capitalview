<?php

namespace App\Http\Controllers;

use App\Models\Emprestimo;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Auth;

class EmprestimoReciboController extends Controller
{
    public function download(Emprestimo $emprestimo)
    {
        // Garante que o empréstimo é do usuário autenticado
        if ((int)$emprestimo->user_id !== (int)Auth::id()) {
            abort(403, 'Você não tem permissão para visualizar este recibo.');
        }

        $user = Auth::user();

        // Credor: pega do usuário logado e cai para o .env/config se faltar algo
        $credor = [
            'nome'   => $user->name
                        ?? config('app.recibo_credor_nome', config('app.name')),
            'cpf'    => $user->cpf
                        ?? config('app.recibo_credor_cpf'),      // pode ser null
            'cidade' => $user->cidade
                        ?? config('app.recibo_cidade'),           // se não tiver esses campos no users, tudo bem
            'uf'     => $user->uf
                        ?? config('app.recibo_uf'),
        ];

        $cliente = $emprestimo->cliente;                // relação emprestimo->cliente
        $valor   = (float) $emprestimo->valor_principal;

        $data = [
            'emprestimo' => $emprestimo,
            'cliente'    => $cliente,
            'credor'     => $credor,
            'valor'      => $valor,
            'hoje'       => now(),
        ];

        $html = view('recibos.emprestimo', $data)->render();

        // Dompdf com opções seguras para nosso HTML
        $options = new Options([
            'isRemoteEnabled' => true,             // pode deixar true; se não usa imagens remotas, pode ser false
            'defaultFont'     => 'DejaVu Sans',    // evita tofu em PT-BR
            'chroot'          => public_path(),    // permite referências locais (ex.: /assets/...)
        ]);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = 'recibo-emprestimo-' . $emprestimo->id . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
