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

        // Se existir status no empréstimo, podemos excluir quitados (segurança extra)
        $hasEmprestimoStatus = Schema::hasColumn('emprestimos', 'status');

        // “Parcela ajustada” (se existir) senão usa valor_parcela
        $parcelaValor = function ($p): float {
            $parcelaAjustada = $p->valor_parcela_ajustada ?? null;
            return (float) ($parcelaAjustada !== null ? $parcelaAjustada : $p->valor_parcela);
        };

        // “Total pago” robusto em uma parcela:
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

        // ================= PERÍODO (chips + datas) =================
        // Prioridade:
        // 1) de/ate
        // 2) periodo (hoje|7d|mes|30d)
        // 3) default: mês atual

        $today = Carbon::today();

        if ($request->filled('de') || $request->filled('ate')) {
            $rangeStart = $request->filled('de')
                ? Carbon::parse($request->input('de'))->startOfDay()
                : Carbon::now()->startOfMonth()->startOfDay();

            $rangeEnd = $request->filled('ate')
                ? Carbon::parse($request->input('ate'))->endOfDay()
                : Carbon::now()->endOfMonth()->endOfDay();
        } else {
            $periodo = strtolower((string) $request->input('periodo', 'mes'));

            if ($periodo === 'hoje') {
                $rangeStart = $today->copy()->startOfDay();
                $rangeEnd   = $today->copy()->endOfDay();
            } elseif ($periodo === '7d') {
                $rangeStart = $today->copy()->subDays(6)->startOfDay();
                $rangeEnd   = $today->copy()->endOfDay();
            } elseif ($periodo === '30d') {
                $rangeStart = $today->copy()->subDays(29)->startOfDay();
                $rangeEnd   = $today->copy()->endOfDay();
            } else {
                // 'mes' ou qualquer outro valor
                $rangeStart = Carbon::now()->startOfMonth()->startOfDay();
                $rangeEnd   = Carbon::now()->endOfMonth()->endOfDay();
            }
        }

        $start = $rangeStart->toDateString();
        $end   = $rangeEnd->toDateString();

        // ================= FILTROS (status + busca) =================

        $statusFiltro = strtolower((string) $request->input('status', '')); // aberto|vencer|atraso|quitado
        $q = trim((string) $request->input('q', ''));

        $applySearchFilter = function ($query) use ($q) {
            if ($q === '') return $query;

            return $query->where(function ($w) use ($q) {
                // busca por número da parcela
                $w->orWhere('numero', 'like', "%{$q}%");

                // busca por ID do empréstimo
                $w->orWhere('emprestimo_id', 'like', "%{$q}%");

                // busca por nome do cliente
                $w->orWhereHas('emprestimo.cliente', function ($qc) use ($q) {
                    $qc->where('nome', 'like', "%{$q}%");
                });
            });
        };

        // ================= BASE QUERIES =================

        // Query base de parcelas com soma de pagamentos
        $parcelasBase = Parcela::query()
            ->withSum('pagamentos', 'valor')
            ->with('emprestimo.cliente');

        // Se existir status no empréstimo, exclui empréstimos quitados (extra) quando estivermos tratando "em aberto"
        $applyEmprestimoNaoQuitado = function ($query) use ($hasEmprestimoStatus) {
            if (!$hasEmprestimoStatus) return $query;

            return $query->whereHas('emprestimo', function ($q) {
                $q->whereRaw('LOWER(status) != ?', ['quitado']);
            });
        };

        // Query de parcelas “em aberto” (padrão do dashboard)
        $parcelasEmAbertoQuery = (clone $parcelasBase)
            ->whereRaw('LOWER(status) != ?', ['paga']);
        $parcelasEmAbertoQuery = $applyEmprestimoNaoQuitado($parcelasEmAbertoQuery);

        // ================= KPIs (mantém sua lógica original) =================

        // Total emprestado (apenas empréstimos que ainda estão em aberto)
        $totalEmprestado = (float) Emprestimo::query()
            ->whereHas('parcelas', function ($q) {
                $q->whereRaw('LOWER(status) != ?', ['paga']);
            })
            ->sum('valor_principal');

        // Em aberto: soma do saldo das parcelas pendentes
        $aberto = (float) (clone $parcelasEmAbertoQuery)
            ->get()
            ->sum(fn ($p) => $saldoAtual($p));

        // Vence em 30 dias: saldo das parcelas pendentes com vencimento <= hoje+30 (e >= hoje)
        $ate30 = (float) (clone $parcelasEmAbertoQuery)
            ->whereDate('vencimento', '>=', now()->toDateString())
            ->whereDate('vencimento', '<=', now()->addDays(30)->toDateString())
            ->get()
            ->sum(fn ($p) => $saldoAtual($p));

        // Em atraso: saldo das parcelas pendentes vencidas
        $atraso = (float) (clone $parcelasEmAbertoQuery)
            ->whereDate('vencimento', '<', now()->toDateString())
            ->get()
            ->sum(fn ($p) => $saldoAtual($p));

        // ================= TOTAIS DO GRÁFICO (Emprestado x Pago) =================
        // - Emprestado: igual ao card (carteira aberta)
        // - Pago no período: soma de pagamentos (qualquer valor) cuja DATA DO PAGAMENTO esteja no período,
        //   independente do vencimento da parcela / se foi parcial / juros / etc.

        // Total emprestado no gráfico = mesma métrica do card
        $totalEmprestadoPeriodo = $totalEmprestado;

        // Descobre automaticamente qual coluna de data existe em pagamentos
        $pagamentoDateCol = null;
        foreach (['data_pagamento', 'pago_em', 'data', 'created_at'] as $col) {
            if (Schema::hasColumn('pagamentos', $col)) {
                $pagamentoDateCol = $col;
                break;
            }
        }
        $pagamentoDateCol = $pagamentoDateCol ?: 'created_at';

        $totalPagoPeriodoQuery = Pagamento::query()
            ->whereDate($pagamentoDateCol, '>=', $start)
            ->whereDate($pagamentoDateCol, '<=', $end);

        // Aplica a busca (q) também ao total pago (via relações)
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

        // Status filtro aplicado ao "pago no período" (quando fizer sentido)
        // - quitado: só pagamentos vinculados a parcelas pagas
        // - aberto/vencer/atraso: pagamentos vinculados a parcelas não pagas (mesma lógica do dashboard)
        if ($statusFiltro === 'quitado') {
            $totalPagoPeriodoQuery->whereHas('parcela', function ($qp) {
                $qp->whereRaw('LOWER(status) = ?', ['paga']);
            });
        } elseif (in_array($statusFiltro, ['aberto', 'vencer', 'atraso'], true)) {
            $totalPagoPeriodoQuery->whereHas('parcela', function ($qp) use ($today, $statusFiltro) {
                $qp->whereRaw('LOWER(status) != ?', ['paga']);

                if ($statusFiltro === 'vencer') {
                    $qp->whereDate('vencimento', '>=', $today->toDateString());
                } elseif ($statusFiltro === 'atraso') {
                    $qp->whereDate('vencimento', '<', $today->toDateString());
                }
            });

            // Se existir status do empréstimo e você quiser manter coerência com "em aberto"
            if ($hasEmprestimoStatus) {
                $totalPagoPeriodoQuery->whereHas('parcela.emprestimo', function ($qe) {
                    $qe->whereRaw('LOWER(status) != ?', ['quitado']);
                });
            }
        }

        $totalPagoPeriodo = (float) $totalPagoPeriodoQuery->sum('valor');

        // ================= PRÓXIMOS VENCIMENTOS (RESPEITA O PERÍODO + FILTROS) =================

        $proximasQuery = (clone $parcelasEmAbertoQuery)
            ->whereBetween('vencimento', [$start, $end])
            ->orderBy('vencimento');

        $proximasQuery = $applySearchFilter($proximasQuery);

        // Se o usuário escolher "quitado", mostramos pagas no período (mesmo na tabela)
        if ($statusFiltro === 'quitado') {
            $proximasQuery = (clone $parcelasBase)
                ->with('emprestimo.cliente')
                ->withSum('pagamentos', 'valor')
                ->whereRaw('LOWER(status) = ?', ['paga'])
                ->whereBetween('vencimento', [$start, $end])
                ->orderBy('vencimento');

            $proximasQuery = $applySearchFilter($proximasQuery);
        } elseif ($statusFiltro === 'vencer') {
            $proximasQuery->whereDate('vencimento', '>=', $today->toDateString());
        } elseif ($statusFiltro === 'atraso') {
            $proximasQuery->whereDate('vencimento', '<', $today->toDateString());
        }

        $proximas = $proximasQuery
            ->paginate(10)
            ->appends($request->query());

        // Decora para a view
        $proximas->getCollection()->transform(function ($p) use ($saldoAtual) {
            $p->saldo_atual    = $saldoAtual($p);
            $p->vencimento_fmt = Carbon::parse($p->vencimento)->format('d/m/Y');
            return $p;
        });

        // =================== EXPORTAÇÃO (RESPEITA PERÍODO + FILTROS) ===================
        if ($request->filled('export')) {
            $exp  = strtolower((string) $request->get('export')); // pdf|xlsx|csv
            $what = strtolower((string) $request->get('what', 'vencimentos'));

            if ($what === 'kpis') {
                $rows = [
                    ['Métrica' => 'Total emprestado (aberto)', 'Valor' => $moeda($totalEmprestado)],
                    ['Métrica' => 'Aberto (a receber)',        'Valor' => $moeda($aberto)],
                    ['Métrica' => 'Vence em 30 dias',          'Valor' => $moeda($ate30)],
                    ['Métrica' => 'Em atraso',                 'Valor' => $moeda($atraso)],
                    ['Métrica' => 'Emprestado (carteira)',     'Valor' => $moeda($totalEmprestadoPeriodo)],
                    ['Métrica' => 'Pago no período',           'Valor' => $moeda($totalPagoPeriodo)],
                    ['Métrica' => 'Período',                   'Valor' => Carbon::parse($start)->format('d/m/Y') . ' até ' . Carbon::parse($end)->format('d/m/Y')],
                    ['Métrica' => 'Data pagamento (coluna)',   'Valor' => $pagamentoDateCol],
                ];

                $columns = [
                    ['key' => 'Métrica', 'label' => 'Métrica'],
                    ['key' => 'Valor',   'label' => 'Valor', 'align' => 'right'],
                ];

                $title = 'Dashboard — KPIs';

                if ($exp === 'pdf') {
                    return $exporter->pdf('reports.table', compact('title', 'columns', 'rows'), 'dashboard-kpis.pdf');
                }

                return $exporter->excel(
                    $rows,
                    $exp === 'csv' ? 'dashboard-kpis.csv' : 'dashboard-kpis.xlsx'
                );
            }

            // ---- Vencimentos (no período, sem paginação) ----
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

            $title = 'Dashboard — Vencimentos (' .
                Carbon::parse($start)->format('d/m/Y') .
                ' a ' .
                Carbon::parse($end)->format('d/m/Y') .
                ')';

            if ($exp === 'pdf') {
                return $exporter->pdf('reports.table', compact('title', 'columns', 'rows'), 'dashboard-vencimentos.pdf');
            }

            return $exporter->excel(
                $rows,
                $exp === 'csv' ? 'dashboard-vencimentos.csv' : 'dashboard-vencimentos.xlsx'
            );
        }
        // ================= FIM EXPORTAÇÃO =================

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
