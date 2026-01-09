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
        // ================= HELPERS =================

        $moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

        // Se existir status no empréstimo, podemos excluir quitados (segurança extra)
        $hasEmprestimoStatus = Schema::hasColumn('emprestimos', 'status');

        // “Parcela ajustada” (se existir) senão usa valor_parcela
        $parcelaValor = function ($p): float {
            $parcelaAjustada = $p->valor_parcela_ajustada ?? null;
            return (float) ($parcelaAjustada !== null ? $parcelaAjustada : $p->valor_parcela);
        };

        // “Total pago” robusto:
        // 1) total_pago (se existir/preenchido)
        // 2) valor_pago (se existir/preenchido)
        // 3) pagamentos_sum_valor (from withSum)
        // 4) relação pagamentos->sum('valor') (fallback)
        $parcelaPago = function ($p): float {
            if (isset($p->total_pago) && $p->total_pago !== null) {
                return (float) $p->total_pago;
            }
            if (isset($p->valor_pago) && $p->valor_pago !== null) {
                return (float) $p->valor_pago;
            }
            if (isset($p->pagamentos_sum_valor) && $p->pagamentos_sum_valor !== null) {
                return (float) $p->pagamentos_sum_valor;
            }
            if (isset($p->pagamentos)) {
                return (float) $p->pagamentos->sum('valor');
            }
            return 0.0;
        };

        // Saldo atual (nunca negativo)
        $saldoAtual = function ($p) use ($parcelaValor, $parcelaPago): float {
            $valor = $parcelaValor($p);
            $pago  = $parcelaPago($p);
            return max(0, $valor - $pago);
        };

        // Base query de parcelas "em aberto" (mais segura)
        $parcelasEmAbertoQuery = Parcela::query()
            // se existir relação pagamentos, já traz soma por SQL
            ->withSum('pagamentos', 'valor')
            // filtra status != paga (case-insensitive)
            ->whereRaw('LOWER(status) != ?', ['paga']);

        // Se existir status no empréstimo, exclui empréstimos quitados (extra)
        if ($hasEmprestimoStatus) {
            $parcelasEmAbertoQuery->whereHas('emprestimo', function ($q) {
                $q->whereRaw('LOWER(status) != ?', ['quitado']);
            });
        }

        // ================= KPIs =================

        // Total emprestado (apenas empréstimos que ainda estão em aberto)
        $totalEmprestado = (float) Emprestimo::query()
            ->whereHas('parcelas', function ($q) {
                $q->whereRaw('LOWER(status) != ?', ['paga']);
            })
            ->sum('valor_principal');

        // Em aberto: soma do saldo das parcelas pendentes
        $aberto = (float) (clone $parcelasEmAbertoQuery)
            ->get()
            ->sum(fn($p) => $saldoAtual($p));

        // Vence em 30 dias: saldo das parcelas pendentes com vencimento <= hoje+30 (e >= hoje)
        $ate30 = (float) (clone $parcelasEmAbertoQuery)
            ->whereDate('vencimento', '>=', now()->toDateString())
            ->whereDate('vencimento', '<=', now()->addDays(30)->toDateString())
            ->get()
            ->sum(fn($p) => $saldoAtual($p));

        // Em atraso: saldo das parcelas pendentes vencidas
        $atraso = (float) (clone $parcelasEmAbertoQuery)
            ->whereDate('vencimento', '<', now()->toDateString())
            ->get()
            ->sum(fn($p) => $saldoAtual($p));

        // ================= PRÓXIMOS VENCIMENTOS (MÊS ATUAL) =================

        $start = Carbon::now()->startOfMonth()->toDateString();
        $end   = Carbon::now()->endOfMonth()->toDateString();

        $proximas = (clone $parcelasEmAbertoQuery)
            ->with('emprestimo.cliente')
            ->whereBetween('vencimento', [$start, $end])
            ->orderBy('vencimento')
            ->paginate(10)
            ->appends($request->query());

        // Decora para a view
        $proximas->getCollection()->transform(function ($p) use ($saldoAtual) {
            $p->saldo_atual    = $saldoAtual($p);
            $p->vencimento_fmt = Carbon::parse($p->vencimento)->format('d/m/Y');
            return $p;
        });

        // =================== EXPORTAÇÃO ===================
        if ($request->filled('export')) {
            $exp  = strtolower((string) $request->get('export')); // pdf|xlsx|csv
            $what = strtolower((string) $request->get('what', 'vencimentos'));

            $hrefExport = function (string $format, string $what) use ($request) {
                return route('dashboard', array_merge($request->all(), [
                    'export' => $format,
                    'what'   => $what,
                ]));
            };

            if ($what === 'kpis') {
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
            $parcelas = (clone $parcelasEmAbertoQuery)
                ->with(['emprestimo.cliente', 'pagamentos'])
                ->whereBetween('vencimento', [$start, $end])
                ->orderBy('vencimento')
                ->get();

            $rows = $parcelas->map(function ($p) use ($moeda, $parcelaValor, $parcelaPago, $saldoAtual) {
                $valor = $parcelaValor($p);
                $pago  = $parcelaPago($p);
                $saldo = $saldoAtual($p);

                return [
                    'Data'         => Carbon::parse($p->vencimento)->format('d/m/Y'),
                    'Cliente'      => $p->emprestimo?->cliente?->nome ?? '—',
                    'Empréstimo #' => $p->emprestimo?->id ?? '—',
                    'Parcela #'    => $p->numero,
                    'Parcela'      => $moeda($valor),
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
