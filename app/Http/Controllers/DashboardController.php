<?php

namespace App\Http\Controllers;

use App\Models\Emprestimo;
use App\Models\Parcela;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request, ReportExportService $exporter)
    {
        // ----- KPIs do topo -----
        $totalEmprestado = (float) Emprestimo::sum('valor_principal');

        $aberto = (float) Parcela::where('status','!=','paga')
            ->get()
            ->sum(function ($p) {
                $parcelaAjustada = $p->valor_parcela_ajustada ?? $p->valor_parcela;
                $totalPago       = $p->total_pago ?? $p->valor_pago;
                return max(0, (float)$parcelaAjustada - (float)$totalPago);
            });

        $ate30 = (float) Parcela::where('status','!=','paga')
            ->whereDate('vencimento','<=', now()->addDays(30))
            ->get()
            ->sum(function ($p) {
                $parcelaAjustada = $p->valor_parcela_ajustada ?? $p->valor_parcela;
                $totalPago       = $p->total_pago ?? $p->valor_pago;
                return max(0, (float)$parcelaAjustada - (float)$totalPago);
            });

        $atraso = (float) Parcela::where('status','!=','paga')
            ->whereDate('vencimento','<', now()->toDateString())
            ->get()
            ->sum(function ($p) {
                $parcelaAjustada = $p->valor_parcela_ajustada ?? $p->valor_parcela;
                $totalPago       = $p->total_pago ?? $p->valor_pago;
                return max(0, (float)$parcelaAjustada - (float)$totalPago);
            });

        // ----- Próximos vencimentos: SOMENTE mês atual + paginação -----
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end   = Carbon::now()->endOfMonth()->toDateString();

        $proximas = Parcela::with('emprestimo.cliente')
            ->where('status','!=','paga')
            ->whereBetween('vencimento', [$start, $end])
            ->orderBy('vencimento')
            ->paginate(10)
            ->appends($request->query());

        // Decora para a view
        $proximas->getCollection()->transform(function ($p) {
            $parcelaAjustada = $p->valor_parcela_ajustada ?? $p->valor_parcela;
            $totalPago       = $p->total_pago ?? $p->valor_pago;
            $p->saldo_atual    = max(0, (float)$parcelaAjustada - (float)$totalPago);
            $p->vencimento_fmt = Carbon::parse($p->vencimento)->format('d/m/Y');
            return $p;
        });

        // =================== EXPORTAÇÃO ===================
        if ($request->filled('export')) {
            $exp  = strtolower((string) $request->get('export')); // pdf|xlsx|csv
            $what = strtolower((string) $request->get('what', 'vencimentos'));

            // Helpers
            $moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

            if ($what === 'kpis') {
                // ---- KPIs ----
                $rows = [
                    ['Métrica' => 'Total emprestado', 'Valor' => $moeda($totalEmprestado)],
                    ['Métrica' => 'Aberto (a receber)', 'Valor' => $moeda($aberto)],
                    ['Métrica' => 'Vence em 30 dias',   'Valor' => $moeda($ate30)],
                    ['Métrica' => 'Em atraso',          'Valor' => $moeda($atraso)],
                ];
                $columns = [
                    ['key'=>'Métrica','label'=>'Métrica'],
                    ['key'=>'Valor','label'=>'Valor','align'=>'right'],
                ];
                $title = 'Dashboard — KPIs';

                if ($exp === 'pdf') {
                    return $exporter->pdf('reports.table', compact('title','columns','rows'), 'dashboard-kpis.pdf');
                }
                return $exporter->excel($rows, $exp === 'csv' ? 'dashboard-kpis.csv' : 'dashboard-kpis.xlsx');
            }

            // ---- Próximos vencimentos (mês atual, sem paginação) ----
            $parcelas = Parcela::with(['emprestimo.cliente', 'pagamentos'])
                ->where('status','!=','paga')
                ->whereBetween('vencimento', [$start, $end])
                ->orderBy('vencimento')
                ->get();

            $rows = $parcelas->map(function ($p) use ($moeda) {
                $parcelaAjustada = $p->valor_parcela_ajustada ?? $p->valor_parcela;
                // total pago: tenta accessor/coluna; se indisponível, soma da relação
                $pago = (float) (
                    $p->total_pago
                    ?? $p->valor_pago
                    ?? optional($p->pagamentos)->sum('valor')
                    ?? 0
                );
                $saldo = max(0, (float)$parcelaAjustada - $pago);

                return [
                    'Data'         => Carbon::parse($p->vencimento)->format('d/m/Y'),
                    'Cliente'      => $p->emprestimo?->cliente?->nome ?? '—',
                    'Empréstimo #' => $p->emprestimo?->id ?? '—',
                    'Parcela #'    => $p->numero,
                    'Parcela'      => $moeda($parcelaAjustada),
                    'Pago'         => $moeda($pago),
                    'Saldo'        => $moeda($saldo),
                ];
            })->values()->all();

            $columns = [
                ['key'=>'Data','label'=>'Data'],
                ['key'=>'Cliente','label'=>'Cliente'],
                ['key'=>'Empréstimo #','label'=>'Empréstimo #','align'=>'right'],
                ['key'=>'Parcela #','label'=>'Parcela #','align'=>'right'],
                ['key'=>'Parcela','label'=>'Parcela','align'=>'right'],
                ['key'=>'Pago','label'=>'Pago','align'=>'right'],
                ['key'=>'Saldo','label'=>'Saldo','align'=>'right'],
            ];

            $title = 'Dashboard — Próximos Vencimentos (mês atual)';

            if ($exp === 'pdf') {
                return $exporter->pdf('reports.table', compact('title','columns','rows'), 'dashboard-vencimentos.pdf');
            }
            return $exporter->excel($rows, $exp === 'csv' ? 'dashboard-vencimentos.csv' : 'dashboard-vencimentos.xlsx');
        }
        // ================= FIM EXPORTAÇÃO =================

        return view('dashboard', compact(
            'totalEmprestado', 'aberto', 'ate30', 'atraso', 'proximas'
        ));
    }
}
