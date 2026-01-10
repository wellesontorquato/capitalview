<?php

namespace App\Http\Controllers;

use App\Models\Emprestimo;
use App\Models\Parcela;
use App\Models\Pagamento;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request, ReportExportService $exporter)
    {
        // ================= HELPERS =================

        $moeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

        $hasEmprestimoStatus = Schema::hasColumn('emprestimos', 'status');

        $parcelaValor = function ($p): float {
            $parcelaAjustada = $p->valor_parcela_ajustada ?? null;
            return (float) ($parcelaAjustada !== null ? $parcelaAjustada : $p->valor_parcela);
        };

        $parcelaPago = function ($p): float {
            if (isset($p->total_pago) && $p->total_pago !== null) return (float) $p->total_pago;
            if (isset($p->valor_pago) && $p->valor_pago !== null) return (float) $p->valor_pago;
            if (isset($p->pagamentos_sum_valor) && $p->pagamentos_sum_valor !== null) return (float) $p->pagamentos_sum_valor;
            if (isset($p->pagamentos)) return (float) $p->pagamentos->sum('valor');
            return 0.0;
        };

        $saldoAtual = function ($p) use ($parcelaValor, $parcelaPago): float {
            $valor = $parcelaValor($p);
            $pago  = $parcelaPago($p);
            return max(0, $valor - $pago);
        };

        // ================= PERÍODO (GRÁFICO) =================
        // Prioridade:
        // 1) de/ate
        // 2) periodo (hoje|7d|mes|30d)
        // 3) default: do PRIMEIRO PAGAMENTO (pagamentos.pago_em) até HOJE
        //    (se não existir pagamento, default = mês atual)

        $today = Carbon::today();

        $minPagoEm = Pagamento::query()->min('pago_em'); // BelongsToUser => multitenancy ok
        $defaultStart = $minPagoEm
            ? Carbon::parse($minPagoEm)->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();

        $defaultEnd = $today->copy()->endOfDay();

        if ($request->filled('de') || $request->filled('ate')) {
            $rangeStart = $request->filled('de')
                ? Carbon::parse($request->input('de'))->startOfDay()
                : $defaultStart->copy();

            $rangeEnd = $request->filled('ate')
                ? Carbon::parse($request->input('ate'))->endOfDay()
                : $defaultEnd->copy();
        } else {
            $periodo = strtolower((string) $request->input('periodo', ''));

            if ($periodo === 'hoje') {
                $rangeStart = $today->copy()->startOfDay();
                $rangeEnd   = $today->copy()->endOfDay();
            } elseif ($periodo === '7d') {
                $rangeStart = $today->copy()->subDays(6)->startOfDay();
                $rangeEnd   = $today->copy()->endOfDay();
            } elseif ($periodo === '30d') {
                $rangeStart = $today->copy()->subDays(29)->startOfDay();
                $rangeEnd   = $today->copy()->endOfDay();
            } elseif ($periodo === 'mes') {
                $rangeStart = Carbon::now()->startOfMonth()->startOfDay();
                $rangeEnd   = Carbon::now()->endOfMonth()->endOfDay();
            } else {
                $rangeStart = $defaultStart->copy();
                $rangeEnd   = $defaultEnd->copy();
            }
        }

        if ($rangeStart->greaterThan($rangeEnd)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
        }

        $start = $rangeStart->toDateString();
        $end   = $rangeEnd->toDateString();

        // ================= FILTROS (status + busca) =================

        $statusFiltro = strtolower((string) $request->input('status', '')); // aberto|vencer|atraso|quitado
        $q = trim((string) $request->input('q', ''));

        $applySearchFilterParcelas = function ($query) use ($q) {
            if ($q === '') return $query;

            return $query->where(function ($w) use ($q) {
                $w->orWhere('numero', 'like', "%{$q}%");
                $w->orWhere('emprestimo_id', 'like', "%{$q}%");
                $w->orWhereHas('emprestimo.cliente', function ($qc) use ($q) {
                    $qc->where('nome', 'like', "%{$q}%");
                });
            });
        };

        // ================= BASE QUERIES =================

        $parcelasBase = Parcela::query()
            ->withSum('pagamentos', 'valor')
            ->with('emprestimo.cliente');

        $applyEmprestimoNaoQuitado = function ($query) use ($hasEmprestimoStatus) {
            if (!$hasEmprestimoStatus) return $query;

            return $query->whereHas('emprestimo', function ($q) {
                $q->whereRaw("LOWER(TRIM(status)) != ?", ['quitado']);
            });
        };

        $parcelasEmAbertoQuery = (clone $parcelasBase)
            ->whereRaw("LOWER(TRIM(status)) != ?", ['paga']);
        $parcelasEmAbertoQuery = $applyEmprestimoNaoQuitado($parcelasEmAbertoQuery);

        // ================= KPIs =================

        $totalEmprestado = (float) Emprestimo::query()
            ->whereHas('parcelas', function ($q) {
                $q->whereRaw("LOWER(TRIM(status)) != ?", ['paga']);
            })
            ->sum('valor_principal');

        $aberto = (float) (clone $parcelasEmAbertoQuery)
            ->get()
            ->sum(fn ($p) => $saldoAtual($p));

        $ate30 = (float) (clone $parcelasEmAbertoQuery)
            ->whereDate('vencimento', '>=', now()->toDateString())
            ->whereDate('vencimento', '<=', now()->addDays(30)->toDateString())
            ->get()
            ->sum(fn ($p) => $saldoAtual($p));

        $atraso = (float) (clone $parcelasEmAbertoQuery)
            ->whereDate('vencimento', '<', now()->toDateString())
            ->get()
            ->sum(fn ($p) => $saldoAtual($p));

        // ================= GRÁFICO (Emprestado x Pago no período) =================

        $emprestimoDateCol = Schema::hasColumn('emprestimos', 'data_emprestimo')
            ? 'data_emprestimo'
            : 'created_at';

        $emprestimosPeriodoQuery = Emprestimo::query()
            ->whereDate($emprestimoDateCol, '>=', $start)
            ->whereDate($emprestimoDateCol, '<=', $end);

        if ($hasEmprestimoStatus) {
            if ($statusFiltro === 'quitado') {
                $emprestimosPeriodoQuery->whereRaw("LOWER(TRIM(status)) = ?", ['quitado']);
            } elseif (in_array($statusFiltro, ['aberto', 'vencer', 'atraso'], true)) {
                $emprestimosPeriodoQuery->whereRaw("LOWER(TRIM(status)) != ?", ['quitado']);
            }
        }

        if ($q !== '') {
            $emprestimosPeriodoQuery->where(function ($w) use ($q) {
                $w->orWhere('id', 'like', "%{$q}%")
                  ->orWhere('cliente_id', 'like', "%{$q}%")
                  ->orWhereHas('cliente', function ($qc) use ($q) {
                      $qc->where('nome', 'like', "%{$q}%");
                  });
            });
        }

        $totalEmprestadoPeriodo = (float) $emprestimosPeriodoQuery->sum('valor_principal');

        $totalPagoPeriodoQuery = Pagamento::query()
            ->whereDate('pago_em', '>=', $start)
            ->whereDate('pago_em', '<=', $end);

        if ($q !== '') {
            $totalPagoPeriodoQuery->whereHas('parcela', function ($qp) use ($q) {
                $qp->where(function ($w) use ($q) {
                    $w->orWhere('numero', 'like', "%{$q}%")
                      ->orWhere('emprestimo_id', 'like', "%{$q}%")
                      ->orWhereHas('emprestimo.cliente', function ($qc) use ($q) {
                          $qc->where('nome', 'like', "%{$q}%");
                      });
                });
            });
        }

        if ($statusFiltro === 'quitado') {
            $totalPagoPeriodoQuery->whereHas('parcela', function ($qp) {
                $qp->whereRaw("LOWER(TRIM(status)) = ?", ['paga']);
            });
        } elseif (in_array($statusFiltro, ['aberto', 'vencer', 'atraso'], true)) {
            $totalPagoPeriodoQuery->whereHas('parcela', function ($qp) use ($today, $statusFiltro) {
                $qp->whereRaw("LOWER(TRIM(status)) != ?", ['paga']);

                if ($statusFiltro === 'vencer') {
                    $qp->whereDate('vencimento', '>=', $today->toDateString());
                } elseif ($statusFiltro === 'atraso') {
                    $qp->whereDate('vencimento', '<', $today->toDateString());
                }
            });

            if ($hasEmprestimoStatus) {
                $totalPagoPeriodoQuery->whereHas('parcela.emprestimo', function ($qe) {
                    $qe->whereRaw("LOWER(TRIM(status)) != ?", ['quitado']);
                });
            }
        }

        $totalPagoPeriodo = (float) $totalPagoPeriodoQuery->sum('valor');

        // ================= PRÓXIMOS VENCIMENTOS (SEMPRE MÊS ATUAL) =================
        // Aqui está o ajuste que “desquebrou” o bloco do mês atual.

        $mesStart = Carbon::now()->startOfMonth()->toDateString();
        $mesEnd   = Carbon::now()->endOfMonth()->toDateString();

        $proximasQuery = (clone $parcelasEmAbertoQuery)
            ->whereBetween('vencimento', [$mesStart, $mesEnd])
            ->orderBy('vencimento');

        $proximasQuery = $applySearchFilterParcelas($proximasQuery);

        if ($statusFiltro === 'quitado') {
            $proximasQuery = (clone $parcelasBase)
                ->with('emprestimo.cliente')
                ->withSum('pagamentos', 'valor')
                ->whereRaw("LOWER(TRIM(status)) = ?", ['paga'])
                ->whereBetween('vencimento', [$mesStart, $mesEnd])
                ->orderBy('vencimento');

            $proximasQuery = $applySearchFilterParcelas($proximasQuery);
        } elseif ($statusFiltro === 'vencer') {
            $proximasQuery->whereDate('vencimento', '>=', $today->toDateString());
        } elseif ($statusFiltro === 'atraso') {
            $proximasQuery->whereDate('vencimento', '<', $today->toDateString());
        }

        $proximas = $proximasQuery
            ->paginate(10)
            ->appends($request->query());

        $proximas->getCollection()->transform(function ($p) use ($saldoAtual) {
            $p->saldo_atual    = $saldoAtual($p);
            $p->vencimento_fmt = Carbon::parse($p->vencimento)->format('d/m/Y');
            return $p;
        });

        // =================== EXPORTAÇÃO ===================
        if ($request->filled('export')) {
            $exp  = strtolower((string) $request->get('export')); // pdf|xlsx|csv
            $what = strtolower((string) $request->get('what', 'vencimentos'));

            if ($what === 'kpis') {
                $rows = [
                    ['Métrica' => 'Total emprestado (aberto)',        'Valor' => $moeda($totalEmprestado)],
                    ['Métrica' => 'Aberto (a receber)',               'Valor' => $moeda($aberto)],
                    ['Métrica' => 'Vence em 30 dias',                 'Valor' => $moeda($ate30)],
                    ['Métrica' => 'Em atraso',                        'Valor' => $moeda($atraso)],
                    ['Métrica' => 'Emprestado no período (gráfico)',  'Valor' => $moeda($totalEmprestadoPeriodo)],
                    ['Métrica' => 'Pago no período (pago_em)',        'Valor' => $moeda($totalPagoPeriodo)],
                    ['Métrica' => 'Período do gráfico',               'Valor' => Carbon::parse($start)->format('d/m/Y') . ' até ' . Carbon::parse($end)->format('d/m/Y')],
                    ['Métrica' => 'Data empréstimo (coluna)',         'Valor' => $emprestimoDateCol],
                    ['Métrica' => 'Vencimentos (mês atual)',          'Valor' => Carbon::parse($mesStart)->format('d/m/Y') . ' até ' . Carbon::parse($mesEnd)->format('d/m/Y')],
                ];

                $columns = [
                    ['key' => 'Métrica', 'label' => 'Métrica'],
                    ['key' => 'Valor',   'label' => 'Valor', 'align' => 'right'],
                ];

                $title = 'Dashboard — KPIs';

                if ($exp === 'pdf') {
                    return $exporter->pdf('reports.table', compact('title', 'columns', 'rows'), 'dashboard-kpis.pdf');
                }

                return $exporter->excel($rows, $exp === 'csv' ? 'dashboard-kpis.csv' : 'dashboard-kpis.xlsx');
            }

            // Export do bloco de vencimentos = mês atual (igual à tabela)
            $parcelasExportQuery = (clone $proximasQuery)->with(['emprestimo.cliente', 'pagamentos']);
            $parcelas = $parcelasExportQuery->get();

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
                ['key' => 'Data',         'label' => 'Data'],
                ['key' => 'Cliente',      'label' => 'Cliente'],
                ['key' => 'Empréstimo #', 'label' => 'Empréstimo #', 'align' => 'right'],
                ['key' => 'Parcela #',    'label' => 'Parcela #',    'align' => 'right'],
                ['key' => 'Parcela',      'label' => 'Parcela',      'align' => 'right'],
                ['key' => 'Pago',         'label' => 'Pago',         'align' => 'right'],
                ['key' => 'Saldo',        'label' => 'Saldo',        'align' => 'right'],
            ];

            $title = 'Dashboard — Vencimentos (Mês atual: ' .
                Carbon::parse($mesStart)->format('d/m/Y') . ' a ' . Carbon::parse($mesEnd)->format('d/m/Y') . ')';

            if ($exp === 'pdf') {
                return $exporter->pdf('reports.table', compact('title', 'columns', 'rows'), 'dashboard-vencimentos.pdf');
            }

            return $exporter->excel($rows, $exp === 'csv' ? 'dashboard-vencimentos.csv' : 'dashboard-vencimentos.xlsx');
        }

        return view('dashboard', compact(
            'totalEmprestado',
            'aberto',
            'ate30',
            'atraso',
            'proximas',
            'totalEmprestadoPeriodo',
            'totalPagoPeriodo',
            'start',
            'end'
        ));
    }
}
