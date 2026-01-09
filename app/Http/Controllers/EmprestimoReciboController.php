<?php

namespace App\Http\Controllers;

use App\Models\Emprestimo;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmprestimoReciboController extends Controller
{
    public function download(Request $request, Emprestimo $emprestimo)
    {
        // Garante que o empréstimo é do usuário autenticado
        if ((int)$emprestimo->user_id !== (int)Auth::id()) {
            abort(403, 'Você não tem permissão para visualizar este recibo.');
        }

        $user = Auth::user();

        // ✅ Observação vinda do modal (?obs=...)
        $observacao = trim((string) $request->query('obs', ''));
        // normaliza quebras e espaços
        $observacao = preg_replace("/\r\n|\r/", "\n", $observacao);
        $observacao = preg_replace("/[ \t]+/", " ", $observacao);

        // limita tamanho (ajuste se quiser)
        if (mb_strlen($observacao) > 600) {
            $observacao = mb_substr($observacao, 0, 600);
        }

        // Credor: pega do usuário logado e cai para o .env/config se faltar algo
        $credor = [
            'nome'   => $user->name ?? config('app.recibo_credor_nome', config('app.name')),
            'cpf'    => $user->cpf  ?? config('app.recibo_credor_cpf'),
            'cidade' => $user->cidade ?? config('app.recibo_cidade'),
            'uf'     => $user->uf ?? config('app.recibo_uf'),
        ];

        $cliente = $emprestimo->cliente;
        $valor   = (float) $emprestimo->valor_principal;

        $data = [
            'emprestimo'  => $emprestimo,
            'cliente'     => $cliente,
            'credor'      => $credor,
            'valor'       => $valor,
            'hoje'        => now(),
            'observacao'  => $observacao, // ✅ passa pra view
        ];

        $html = view('recibos.emprestimo', $data)->render();

        $options = new Options([
            'isRemoteEnabled' => true,
            'defaultFont'     => 'DejaVu Sans',
            'chroot'          => public_path(),
        ]);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = 'recibo-emprestimo-' . $emprestimo->id
          . ($observacao ? '-com-observacao' : '')
          . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
