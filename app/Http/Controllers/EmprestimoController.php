<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Emprestimo;
use App\Models\Parcela;
use App\Models\Pagamento;
use App\Services\LoanCalculator;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class EmprestimoController extends Controller
{
    /** LISTAGEM + BUSCA + FILTROS */
    public function index(Request $request, ReportExportService $exporter)
    {
        $q       = trim((string) $request->get('q'));
        $statusF = $request->get('status'); // 'aberto' | 'quitado' | null

        // coluna de vencimento (para badge "Atrasado")
        $dueCol = Schema::hasColumn('parcelas', 'vence_em')
            ? 'vence_em'
            : (Schema::hasColumn('parcelas', 'vencimento') ? 'vencimento' : null);

        $emprestimos = Emprestimo::query()
            ->with('cliente')
            // contadores p/ badges
            ->withCount([
                'parcelas as abertas_count' => function ($q2) {
                    $q2->where('status', '!=', 'paga');
                },
                'parcelas as vencidas_count' => function ($q2) use ($dueCol) {
                    $q2->where('status', '!=', 'paga');
                    if ($dueCol) {
                        $q2->whereDate($dueCol, '<', now()->toDateString());
                    } else {
                        $q2->whereRaw('0=1');
                    }
                },
                'parcelas as pagamentos_count' => function ($q2) {
                    $q2->whereHas('pagamentos');
                },
            ])
            // busca (agrupada para não afetar outros filtros)
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($q1) use ($q) {
                    $q1->where('id', (int) $q)
                    ->orWhereHas('cliente', fn($qc) => $qc->where('nome', 'like', "%{$q}%"));
                });
            })
            // filtros
            ->when($statusF === 'aberto', function ($qb) {
                $qb->whereHas('parcelas', fn($q) => $q->where('status', '!=', 'paga'));
            })
            ->when($statusF === 'quitado', function ($qb) {
                $qb->whereDoesntHave('parcelas', fn($q) => $q->where('status', '!=', 'paga'));
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        // enriquecer com lucro (juros recebidos) e "quitado_por"
        $emprestimos->getCollection()->load(['parcelas.pagamentos']);
        $emprestimos->getCollection()->transform(function ($e) {
            // total efetivamente pago (preferir tabela pagamentos; fallback valor_pago)
            $totalPagoPg = (float) $e->parcelas->flatMap->pagamentos->sum('valor');
            $totalPagoVp = (float) $e->parcelas->sum('valor_pago');
            $totalPago   = $totalPagoPg > 0 ? $totalPagoPg : $totalPagoVp;

            // principal restante = soma das amortizações das parcelas ainda abertas
            $principalRestante    = (float) $e->parcelas->where('status', '!=', 'paga')->sum('valor_amortizacao');
            $principalReembolsado = max(0.0, (float) $e->valor_principal - $principalRestante);

            // LUCRO: apenas juros recebidos
            $e->retorno_lucro = max(0.0, round($totalPago - $principalReembolsado, 2));

            // "por quanto foi quitado" (somatório na última data de pagamento)
            if ((int) ($e->abertas_count ?? 0) === 0) {
                $pags = $e->parcelas->flatMap->pagamentos;
                $last = optional($pags->sortBy('pago_em')->last())->pago_em;
                $e->quitado_por = $last ? (float) $pags->where('pago_em', $last)->sum('valor') : 0.0;
            } else {
                $e->quitado_por = null;
            }
            return $e;
        });

        // EXPORTAÇÃO (GERAL) — usa coleção já enriquecida
        if ($request->filled('export')) {
            $rows = $emprestimos->getCollection()->map(function ($e) {
                $moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
                $pct   = fn($x) => is_numeric($x) ? number_format($x*100, 2, ',', '.') . ' % a.m.' : '—';

                return [
                    '#'           => $e->id,
                    'Cliente'     => $e->cliente?->nome ?? '—',
                    'Tipo'        => $e->tipo_calculo === 'FIXED_ON_PRINCIPAL' ? 'Opção A' : 'Opção B',
                    'Taxa'        => $pct($e->taxa_periodo),
                    'Parcelas'    => (string) ($e->qtd_parcelas ?? '—'),
                    'Empréstimo'  => $moeda($e->valor_principal),
                    'Status'      => ($e->abertas_count ?? 0) ? 'Em aberto' : 'Quitado',
                    'Retorno'     => $moeda($e->retorno_lucro ?? 0),
                    'Quitado por' => $e->quitado_por !== null ? $moeda($e->quitado_por) : '—',
                ];
            })->values()->all();

            $columns = [
                ['key'=>'#','label'=>'#','align'=>'right'],
                ['key'=>'Cliente','label'=>'Cliente'],
                ['key'=>'Tipo','label'=>'Tipo'],
                ['key'=>'Taxa','label'=>'Taxa','align'=>'right'],
                ['key'=>'Parcelas','label'=>'Parcelas','align'=>'right'],
                ['key'=>'Empréstimo','label'=>'Empréstimo','align'=>'right'],
                ['key'=>'Status','label'=>'Status'],
                ['key'=>'Retorno','label'=>'Retorno','align'=>'right'],
                ['key'=>'Quitado por','label'=>'Quitado por','align'=>'right'],
            ];

            $fmt = strtolower((string) $request->get('export'));

            if ($fmt === 'pdf') {
                return $exporter->pdf(
                    'reports.table',
                    ['title' => 'Empréstimos — Lista', 'columns' => $columns, 'rows' => $rows],
                    'emprestimos.pdf',
                    ['landscape' => true]
                );
            }

            // Qualquer “excel/xlsx/csv” → envia CSV estável (abre no Excel)
            return $this->downloadCsv($rows, 'emprestimos.csv');
        }
        
        return view('emprestimos.index', compact('emprestimos'));
    }

    /** NOVO */
    public function create()
    {
        $clientes = Cliente::orderBy('nome')->get();
        return view('emprestimos.create', compact('clientes'));
    }

    /** GRAVAR NOVO */
    public function store(Request $request, LoanCalculator $calc)
    {
        // normaliza taxa (vírgula -> ponto)
        $taxaRaw = $request->input('taxa_mensal', $request->input('taxa_periodo'));
        $taxaFmt = is_string($taxaRaw) ? str_replace(',', '.', $taxaRaw) : $taxaRaw;

        $request->merge([
            'taxa_periodo' => $taxaFmt !== null && $taxaFmt !== '' ? (float) $taxaFmt : null,
        ]);

        $data = $request->validate([
            'cliente_id'              => ['required','exists:clientes,id'],
            'valor_principal'         => ['required','numeric','min:0.01'],
            'taxa_periodo'            => ['required','numeric','min:0'],
            'qtd_parcelas'            => ['required','integer','min:1','max:360'],
            'tipo_calculo'            => ['required','in:FIXED_ON_PRINCIPAL,AMORTIZATION_ON_BALANCE'],
            'primeiro_vencimento'     => ['nullable','date'],
            'observacoes'             => ['nullable','string'],
            'primeira_proporcional'   => ['nullable'], // radio "1" ou "0" vindo da view
        ]);

        return DB::transaction(function () use ($request, $data, $calc) {
            $emp = Emprestimo::create([
                'cliente_id'          => $data['cliente_id'],
                'valor_principal'     => $data['valor_principal'],
                'qtd_parcelas'        => $data['qtd_parcelas'],
                'taxa_periodo'        => $data['taxa_periodo'],
                'tipo_calculo'        => $data['tipo_calculo'],
                'primeiro_vencimento' => $data['primeiro_vencimento'] ?? null,
                'observacoes'         => $data['observacoes'] ?? null,
                'status'              => 'ativo',
            ]);

            // gera cronograma (A ou B)
            $schedule = $calc->buildSchedule(
                pv: (float) $emp->valor_principal,
                n:  (int)   $emp->qtd_parcelas,
                i:  (float) $emp->taxa_periodo,
                loanType:   $emp->tipo_calculo,
                firstDueDate: $emp->primeiro_vencimento
            );

            // aplica 1ª proporcional (se marcado e se < 30 dias) — serve para A e B
            $this->aplicarPrimeiraProporcional($schedule, $emp, (bool) ((string)$request->input('primeira_proporcional', '1') === '1'));

            $emp->parcelas()->delete();

            foreach ($schedule as $row) {
                Parcela::create([
                    'emprestimo_id'     => $emp->id,
                    'numero'            => $row['number'],
                    'vencimento'        => $row['due_date'],
                    'valor_amortizacao' => $row['principal'],
                    'valor_juros'       => $row['interest'],
                    'valor_parcela'     => $row['installment'],
                    'saldo_devedor'     => 0.0, // zera e recalcula no final (saldo de abertura)
                    'status'            => 'aberta',
                ]);
            }

            $this->recalcularSaldos($emp);

            return redirect()->route('emprestimos.show', $emp)->with('success', 'Empréstimo criado.');
        });
    }

    /** EXIBIR (inclui export micro de parcelas) */
    public function show(Request $request, Emprestimo $emprestimo, ReportExportService $exporter)
    {
        $emprestimo->load([
            'cliente',
            'parcelas.pagamentos',
            'parcelas.ajustesOrigem',
            'parcelas.ajustesDestino',
        ]);

        // EXPORTAÇÃO MICRO (parcelas do empréstimo)
        if ($request->filled('export')) {
            $exp = strtolower((string) $request->get('export'));

            // PDF explícito; qualquer “excel/xlsx/csv” vira CSV para abrir no Excel
            $isPdf = in_array($exp, ['pdf', 'parcelas-pdf'], true);
            $isCsv = in_array($exp, ['csv', 'parcelas-csv', 'xlsx', 'parcelas-xlsx'], true);

            // coluna de vencimento
            $dueCol = \Illuminate\Support\Facades\Schema::hasColumn('parcelas', 'vence_em')
                ? 'vence_em'
                : (\Illuminate\Support\Facades\Schema::hasColumn('parcelas', 'vencimento') ? 'vencimento' : null);

            $moeda = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

            $rows = $emprestimo->parcelas->map(function ($p) use ($moeda, $dueCol) {
                // total pago: prefere tabela pagamentos; fallback para valor_pago
                $pagoPg = (float) $p->pagamentos->sum('valor');
                $pagoVp = (float) ($p->valor_pago ?? 0);
                $pago   = $pagoPg > 0 ? $pagoPg : $pagoVp;

                $vencFmt = $dueCol && $p->{$dueCol}
                    ? \Illuminate\Support\Carbon::parse($p->{$dueCol})->format('d/m/Y')
                    : '—';

                return [
                    '#'             => $p->numero,
                    'Vencimento'    => $vencFmt,
                    'Amortização'   => $moeda($p->valor_amortizacao),
                    'Juros'         => $moeda($p->valor_juros),
                    'Parcela'       => $moeda($p->valor_parcela),
                    'Saldo Devedor' => $moeda($p->saldo_devedor ?? 0),
                    'Status'        => $p->status,
                    'Pago'          => $moeda($pago),
                ];
            })->values()->all();

            if ($isPdf) {
                $columns = [
                    ['key'=>'#','label'=>'#','align'=>'right'],
                    ['key'=>'Vencimento','label'=>'Vencimento'],
                    ['key'=>'Amortização','label'=>'Amortização','align'=>'right'],
                    ['key'=>'Juros','label'=>'Juros','align'=>'right'],
                    ['key'=>'Parcela','label'=>'Parcela','align'=>'right'],
                    ['key'=>'Saldo Devedor','label'=>'Saldo Devedor','align'=>'right'],
                    ['key'=>'Status','label'=>'Status'],
                    ['key'=>'Pago','label'=>'Pago','align'=>'right'],
                ];
                $title = "Parcelas — Empréstimo #{$emprestimo->id} ({$emprestimo->cliente?->nome})";

                return $exporter->pdf(
                    'reports.table',
                    compact('title', 'columns', 'rows'),
                    "parcelas-emprestimo-{$emprestimo->id}.pdf",
                    ['landscape' => true]
                );
            }

            if ($isCsv) {
                // use o mesmo helper que já funciona no index()
                return $this->downloadCsv($rows, "parcelas-emprestimo-{$emprestimo->id}.csv");
            }
        }


        return view('emprestimos.show', compact('emprestimo'));
    }

    /** EDITAR (view) */
    public function edit(Emprestimo $emprestimo)
    {
        $emprestimo->load('cliente');
        $clientes = Cliente::orderBy('nome')->get();
        return view('emprestimos.edit', compact('emprestimo', 'clientes'));
    }

    /** ATUALIZAR (não recalcula automaticamente o cronograma) */
    public function update(Request $request, Emprestimo $emprestimo)
    {
        // normaliza taxa
        if ($request->has('taxa_mensal')) {
            $taxaRaw = $request->input('taxa_mensal');
            $taxaFmt = is_string($taxaRaw) ? str_replace(',', '.', $taxaRaw) : $taxaRaw;
            $request->merge(['taxa_periodo' => $taxaFmt !== null && $taxaFmt !== '' ? (float) $taxaFmt : null]);
        }

        $data = $request->validate([
            'cliente_id'          => ['required','exists:clientes,id'],
            'valor_principal'     => ['required','numeric','min:0.01'],
            'taxa_periodo'        => ['required','numeric','min:0'],
            'qtd_parcelas'        => ['required','integer','min:1','max:360'],
            'tipo_calculo'        => ['required','in:FIXED_ON_PRINCIPAL,AMORTIZATION_ON_BALANCE'],
            'primeiro_vencimento' => ['nullable','date'],
            'observacoes'         => ['nullable','string'],
        ]);

        $emprestimo->fill($data)->save();

        return redirect()
            ->route('emprestimos.show', $emprestimo)
            ->with('success', 'Empréstimo atualizado. (O cronograma existente não foi recalculado.)');
    }

    /** REMOVER (com "forçar") */
    public function destroy(Request $request, Emprestimo $emprestimo)
    {
        $haPagamentos = Pagamento::whereIn('parcela_id', $emprestimo->parcelas()->pluck('id'))->exists();
        $force = (bool) $request->input('force', false);

        if ($haPagamentos && !$force) {
            return back()->with('error', 'Não é possível excluir: há parcelas com pagamento. Use a confirmação para forçar a exclusão.');
        }

        DB::transaction(function () use ($emprestimo) {
            foreach ($emprestimo->parcelas as $p) {
                $p->pagamentos()->delete();
                if (method_exists($p, 'ajustesOrigem'))  { $p->ajustesOrigem()->delete(); }
                if (method_exists($p, 'ajustesDestino')) { $p->ajustesDestino()->delete(); }
            }
            $emprestimo->parcelas()->delete();
            $emprestimo->delete();
        });

        return redirect()->route('emprestimos.index')->with('success', 'Empréstimo removido.');
    }

    /** (RE)GERAR CRONOGRAMA MANUALMENTE — bloqueia se já houver pagamentos */
    public function gerarCronogramaManual(Request $request, Emprestimo $emprestimo, LoanCalculator $calc)
    {
        $temPag = $emprestimo->parcelas()->whereHas('pagamentos')->exists()
            || (Schema::hasColumn('parcelas', 'valor_pago') && $emprestimo->parcelas()->where('valor_pago','>',0)->exists());

        if ($temPag) {
            return back()->with('error', 'Não é possível gerar/regerar: já há pagamentos.');
        }

        if ($request->has('taxa_mensal')) {
            $taxaFmt = str_replace(',', '.', (string) $request->input('taxa_mensal'));
            $request->merge(['taxa_periodo' => (float) $taxaFmt]);
        }

        $data = $request->validate([
            'qtd_parcelas'          => ['required','integer','min:1','max:360'],
            'primeiro_vencimento'   => ['required','date'],
            'primeira_proporcional' => ['nullable'], // radio "1" ou "0"
        ]);

        $emprestimo->fill($data)->save();

        $schedule = $calc->buildSchedule(
            pv: (float) $emprestimo->valor_principal,
            n:  (int)   $emprestimo->qtd_parcelas,
            i:  (float) $emprestimo->taxa_periodo,
            loanType:   $emprestimo->tipo_calculo,
            firstDueDate: $emprestimo->primeiro_vencimento
        );

        // aplica 1ª proporcional (se marcado e se < 30 dias) — serve para A e B
        $this->aplicarPrimeiraProporcional($schedule, $emprestimo, (bool) ((string)$request->input('primeira_proporcional', '1') === '1'));

        DB::transaction(function () use ($emprestimo, $schedule) {
            $emprestimo->parcelas()->delete();

            foreach ($schedule as $row) {
                Parcela::create([
                    'emprestimo_id'     => $emprestimo->id,
                    'numero'            => $row['number'],
                    'vencimento'        => $row['due_date'],
                    'valor_amortizacao' => $row['principal'],
                    'valor_juros'       => $row['interest'],
                    'valor_parcela'     => $row['installment'],
                    'saldo_devedor'     => 0.0, // zera e recalcula no final (saldo de abertura)
                    'status'            => 'aberta',
                ]);
            }

            $this->recalcularSaldos($emprestimo);
        });

        return back()->with('success', 'Cronograma gerado com sucesso.');
    }

    /** --------- QUITAÇÃO: PREVIEW + EXECUÇÃO (3 MODOS) --------- */

    private function montarQuitacaoPreview(Emprestimo $emprestimo): array
    {
        $colParcelaAjustada = Schema::hasColumn('parcelas', 'valor_parcela_ajustada') ? 'valor_parcela_ajustada' : null;

        $abertas = $emprestimo->parcelas()
            ->where('status','!=','paga')
            ->with('pagamentos')
            ->orderBy('numero')
            ->get();

        $restanteContratual = 0.0;
        foreach ($abertas as $p) {
            $parcelaBase = $colParcelaAjustada ? (float) ($p->valor_parcela_ajustada ?? 0) : (float) ($p->valor_parcela ?? 0);
            $pago        = (float) ($p->pagamentos->sum('valor') + ($p->valor_pago ?? 0));
            $restanteContratual += max(0.0, $parcelaBase - $pago);
        }

        $principalRestante = (float) $abertas->sum('valor_amortizacao');

        $i = (float) $emprestimo->taxa_periodo;
        $jurosMes = $emprestimo->tipo_calculo === 'FIXED_ON_PRINCIPAL'
            ? $i * (float) $emprestimo->valor_principal
            : $i * $principalRestante;

        return [
            'restante_contratual'   => round($restanteContratual, 2),
            'principal_restante'    => round($principalRestante, 2),
            'juros_mes'             => round(max(0, $jurosMes), 2),
            'total_amortizar_agora' => round($principalRestante + max(0, $jurosMes), 2),
            'taxa_periodo'          => (float) $emprestimo->taxa_periodo,
            'moeda'                 => 'BRL',
        ];
    }

    public function quitacaoPreview(Emprestimo $emprestimo)
    {
        return response()->json($this->montarQuitacaoPreview($emprestimo));
    }

    public function quitar(Request $request, Emprestimo $emprestimo)
    {
        $data = $request->validate([
            'modo'                => 'required|in:ACORDADO,AMORTIZACAO,DESCONTO',
            'desconto_percentual' => 'nullable|numeric|min:0|max:100',
            'pago_em'             => 'nullable|date',
            'banco'               => 'nullable|string|in:CAIXA,C6,BRADESCO',
        ]);

        $preview = $this->montarQuitacaoPreview($emprestimo);

        $valorQuitacao = match ($data['modo']) {
            'ACORDADO'    => (float) $preview['restante_contratual'],
            'AMORTIZACAO' => (float) $preview['total_amortizar_agora'],
            'DESCONTO'    => max(0.0, (float) $preview['restante_contratual'] * (1 - (float)($data['desconto_percentual'] ?? 0)/100)),
        };

        $quando = $data['pago_em'] ?? now()->toDateString();
        $banco  = $data['banco'] ?? null;

        $resp = DB::transaction(function () use ($emprestimo, $data, $preview, $valorQuitacao, $quando, $banco) {
            $colParcelaAjustada = Schema::hasColumn('parcelas', 'valor_parcela_ajustada');
            $colValorPago       = Schema::hasColumn('parcelas', 'valor_pago');

            $abertas = $emprestimo->parcelas()
                ->where('status','!=','paga')
                ->with('pagamentos')
                ->orderBy('numero')
                ->get();

            if ($abertas->isEmpty()) {
                return back()->with('success', 'Nada a quitar: todas as parcelas já estão pagas.');
            }

            $novoPagamento = function(Parcela $p, float $valor) use ($quando, $banco) {
                $payload = [
                    'parcela_id' => $p->id,
                    'valor'      => round($valor, 2),
                    'pago_em'    => $quando,
                ];
                if ($banco && Schema::hasColumn('pagamentos', 'banco')) {
                    $payload['banco'] = $banco;
                }
                if (Schema::hasColumn('pagamentos', 'meta')) {
                    $payload['meta'] = 'quitacao_total';
                }
                return $payload;
            };

            if ($data['modo'] === 'ACORDADO') {
                $restante = $valorQuitacao;

                foreach ($abertas as $p) {
                    $parcelaBase = $colParcelaAjustada ? (float) ($p->valor_parcela_ajustada ?? 0) : (float) ($p->valor_parcela ?? 0);
                    $pagoAtual   = (float) ($p->pagamentos->sum('valor') + ($p->valor_pago ?? 0));
                    $saldoParc   = max(0.0, $parcelaBase - $pagoAtual);

                    if ($saldoParc <= 0.009) {
                        $p->status = 'paga';
                        $p->save();
                        continue;
                    }

                    $valor = min($saldoParc, $restante);
                    if ($valor <= 0.0) break;

                    Pagamento::create($novoPagamento($p, $valor));

                    if ($colValorPago) {
                        $p->valor_pago = ($p->valor_pago ?? 0) + round($valor,2);
                    }
                    // marcou paga se atingiu o valor da parcela
                    $p->status = (abs((($p->valor_pago ?? $p->pagamentos->sum('valor')) + 0) - $parcelaBase) <= 0.01) ? 'paga' : $p->status;
                    $p->save();

                    $restante -= $valor;
                    if ($restante <= 0.009) break;
                }

                if (!$emprestimo->parcelas()->where('status','!=','paga')->exists()) {
                    $emprestimo->status = 'quitado';
                    $emprestimo->save();
                }

                $this->recalcularSaldos($emprestimo);

                return back()->with('success', 'Quitação (pelo acordado) registrada com sucesso.');
            }

            if ($data['modo'] === 'AMORTIZACAO') {
                $principalRestante = (float) $preview['principal_restante'];
                $jurosMes          = (float) $preview['juros_mes'];
                $primeira          = true;

                foreach ($abertas as $p) {
                    $amort = round((float) $p->valor_amortizacao, 2);
                    $juros = $primeira ? round($jurosMes, 2) : 0.0;

                    $p->valor_juros   = $juros;
                    $p->valor_parcela = round($amort + $juros, 2);

                    Pagamento::create($novoPagamento($p, $p->valor_parcela));

                    if ($colValorPago) {
                        $p->valor_pago = ($p->valor_pago ?? 0) + $p->valor_parcela;
                    }
                    $p->status = 'paga';
                    $p->save();

                    $principalRestante = max(0.0, $principalRestante - $amort);
                    $primeira = false;
                }

                $emprestimo->status = 'quitado';
                $emprestimo->save();

                $this->recalcularSaldos($emprestimo);

                $msg = sprintf(
                    'Empréstimo quitado por amortização. Total: R$ %s (principal R$ %s + juros do mês R$ %s).',
                    number_format($valorQuitacao, 2, ',', '.'),
                    number_format($preview['principal_restante'], 2, ',', '.'),
                    number_format($preview['juros_mes'], 2, ',', '.')
                );

                return back()->with('success', $msg);
            }

            // DESCONTO
            if ($data['modo'] === 'DESCONTO') {
                $abertasIds      = $abertas->pluck('id')->all();
                $primeiraParcela = $abertas->first();

                if ($colParcelaAjustada) {
                    foreach ($abertas as $pp) {
                        $pp->valor_parcela_ajustada = 0;
                        $pp->save();
                    }
                    $primeiraParcela->valor_parcela_ajustada = round($valorQuitacao, 2);
                    $primeiraParcela->save();
                } else {
                    foreach ($abertas as $idx => $pp) {
                        $pp->valor_parcela = ($idx === 0) ? round($valorQuitacao, 2) : 0.0;
                        $pp->save();
                    }
                }

                Pagamento::create($novoPagamento($primeiraParcela, $valorQuitacao));

                if ($colValorPago) {
                    $primeiraParcela->valor_pago = ($primeiraParcela->valor_pago ?? 0) + round($valorQuitacao, 2);
                    $primeiraParcela->save();
                }

                Parcela::whereIn('id', $abertasIds)->update(['status' => 'paga']);

                $emprestimo->status = 'quitado';
                $emprestimo->save();

                $this->recalcularSaldos($emprestimo);

                $msg = sprintf(
                    'Empréstimo quitado com desconto. Valor pago: R$ %s (desconto sobre R$ %s).',
                    number_format($valorQuitacao, 2, ',', '.'),
                    number_format($preview['restante_contratual'], 2, ',', '.')
                );

                return back()->with('success', $msg);
            }

            return back()->with('error', 'Modo de quitação inválido.');
        });

        return $resp;
    }

    /* ========================= Helpers ========================= */

    /**
     * Aplica proporcionalidade na 1ª parcela (se faltarem <30 dias para o 1º vencimento):
     * - Ajusta SOMENTE os juros da primeira linha por (dias/30).
     * - Mantém a amortização da 1ª parcela.
     * Funciona para ambos os tipos (A e B), pois o schedule já vem com principal/interest separados.
     */
    private function aplicarPrimeiraProporcional(array &$schedule, Emprestimo $emp, bool $aplicar): void
    {
        if (!$aplicar || empty($schedule)) return;

        $due    = Carbon::parse($schedule[0]['due_date']);
        $inicio = Carbon::today();
        $dias   = max(0, $inicio->diffInDays($due, false)); // 0 se hoje >= vencimento

        if ($dias > 0 && $dias < 30) {
            $ratio = $dias / 30.0;

            $interest = (float) ($schedule[0]['interest'] ?? 0);
            $principal = (float) ($schedule[0]['principal'] ?? 0);

            $interest = round($interest * $ratio, 2);
            $install  = round($principal + $interest, 2);

            $schedule[0]['interest']    = $interest;
            $schedule[0]['installment'] = $install;
            // principal permanece inalterado
        }
    }

    /**
     * Recalcula o campo saldo_devedor de TODAS as parcelas do empréstimo,
     * como SALDO DE ABERTURA da linha (antes da amortização da própria linha).
     */
    private function recalcularSaldos(Emprestimo $emprestimo): void
    {
        if (!Schema::hasColumn('parcelas', 'saldo_devedor')) return;

        $parcelas = $emprestimo->parcelas()->orderBy('numero')->get();
        $saldo = (float) $emprestimo->valor_principal;

        foreach ($parcelas as $p) {
            // grava o saldo ANTES da amortização desta linha
            $p->saldo_devedor = round($saldo, 2);
            $p->save();

            // prepara o saldo para a próxima linha
            $saldo = max(0.0, $saldo - (float) $p->valor_amortizacao);
        }
    }

    private function downloadCsv(array $rows, string $filename = 'dados.csv')
    {
        return response()->streamDownload(function () use ($rows) {
            // garante que não há lixo no buffer antes de escrever o CSV
            while (ob_get_level() > 0) { ob_end_clean(); }

            $out = fopen('php://output', 'w');

            // BOM UTF-8 para acentos aparecerem certos no Excel
            fwrite($out, "\xEF\xBB\xBF");

            if (!empty($rows)) {
                // Cabeçalho:
                fputcsv($out, array_keys($rows[0]), ';');

                // Linhas:
                foreach ($rows as $r) {
                    fputcsv($out, $r, ';');
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type'              => 'text/csv; charset=UTF-8',
            'Cache-Control'             => 'no-store, no-cache, must-revalidate',
            'Pragma'                    => 'no-cache',
            'X-Content-Type-Options'    => 'nosniff',
        ]);
    }
}
