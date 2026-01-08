<?php

namespace App\Http\Controllers;

use App\Models\Parcela;
use App\Models\Emprestimo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ParcelaController extends Controller
{
    public function pagar(Request $request, Parcela $parcela)
    {
        $data = $request->validate([
            'modo'               => 'required|in:juros,parcial,total',
            'valor'              => 'required|numeric|min:0.01',
            'pago_em'            => 'nullable|date',
            'destino_parcela_id' => 'nullable|string',   // id existente, vazio ou "__nova__"
            'aplicar_juros'      => 'nullable|boolean',
            'banco'              => 'required|in:CAIXA,C6,BRADESCO',
        ]);

        $data['pago_em'] = $data['pago_em'] ?? now()->toDateString();

        // Guard: não permitir novo pagamento se já quitada ou já tem pagamento
        if ($parcela->status === 'paga' || $parcela->pagamentos()->exists()) {
            return back()->with('error', 'Esta parcela já possui pagamento registrado ou está quitada.');
        }

        $emprestimo = $parcela->emprestimo;
        $i          = (float) $emprestimo->taxa_periodo;

        // Referências (parcela ajustada ou base)
        $valorParcela = (float) ($parcela->valor_parcela_ajustada ?? $parcela->valor_parcela);
        $totalPago    = (float) ($parcela->total_pago ?? 0);
        $saldoParcela = max(0.0, $valorParcela - $totalPago);

        return DB::transaction(function () use ($data, $parcela, $emprestimo, $saldoParcela, $i) {

            // Helper: seta valor_pago somando pagamentos (se a coluna existir)
            $setValorPago = function (Parcela $p) {
                if (!Schema::hasColumn('parcelas', 'valor_pago')) return;
                $p->valor_pago = (float) $p->pagamentos()->sum('valor');
                $p->save();
            };

            /* ========================= MODO: SÓ JUROS ========================= */
            if ($data['modo'] === 'juros') {
                $valorJuros = (float) $parcela->valor_juros;
                $valorPago  = min(max((float)$data['valor'], 0.01), $valorJuros);

                // registra pagamento
                $parcela->pagamentos()->create([
                    'valor'   => $valorPago,
                    'pago_em' => $data['pago_em'],
                    'banco'   => $data['banco'],
                    'modo'    => 'juros',
                ]);

                // cria NOVA com a amortização que ficou + juros do próximo período (regra original)
                $maxNumero  = (int) $emprestimo->parcelas()->max('numero');
                $novoNumero = $maxNumero + 1;

                $ultimoVenc = $emprestimo->parcelas()->reorder()->max('vencimento');
                $baseData   = $ultimoVenc ? Carbon::parse($ultimoVenc) : Carbon::parse($parcela->vencimento ?? now());
                $novoVenc   = $baseData->copy()->addMonthNoOverflow()->toDateString();

                $amortQueFicou = (float) $parcela->valor_amortizacao;

                $principalAberto = (float) $emprestimo->parcelas()
                    ->where('status', '!=', 'paga')
                    ->sum('valor_amortizacao');

                $jurosNovo = $emprestimo->tipo_calculo === 'FIXED_ON_PRINCIPAL'
                    ? $i * (float) $emprestimo->valor_principal
                    : $i * max(0.0, $principalAberto);

                $emprestimo->parcelas()->create([
                    'numero'            => $novoNumero,
                    'vencimento'        => $novoVenc,
                    'valor_amortizacao' => round($amortQueFicou, 2),
                    'valor_juros'       => round($jurosNovo, 2),
                    'valor_parcela'     => round($amortQueFicou + $jurosNovo, 2),
                    'saldo_devedor'     => 0.0,
                    'status'            => 'aberta',
                ]);

                // fecha a atual como paga (juros)
                $parcela->valor_juros       = round($valorPago, 2);
                $parcela->valor_amortizacao = 0.0;
                $parcela->valor_parcela     = round($valorPago, 2);
                $parcela->status            = 'paga';
                $parcela->save();
                $setValorPago($parcela);

                $this->recalcularSaldos($emprestimo);
                return back()->with('success', 'Juros pagos. Amortização empurrada para nova parcela no fim do cronograma.');
            }

            /* ========================= MODO: TOTAL DA PARCELA ========================= */
            if ($data['modo'] === 'total') {
                $valorPago = $saldoParcela;

                $parcela->pagamentos()->create([
                    'valor'   => $valorPago,
                    'pago_em' => $data['pago_em'],
                    'banco'   => $data['banco'],
                    'modo'    => 'total',
                ]);

                // distribui (juros primeiro)
                $jurosPago = min($valorPago, (float) $parcela->valor_juros);
                $amortPago = max(0.0, $valorPago - $jurosPago);

                $parcela->valor_juros       = round($jurosPago, 2);
                $parcela->valor_amortizacao = round($amortPago, 2);
                $parcela->valor_parcela     = round($jurosPago + $amortPago, 2);
                $parcela->status            = 'paga';
                $parcela->save();
                $setValorPago($parcela);

                $this->recalcularSaldos($emprestimo);
                return back()->with('success', 'Parcela quitada.');
            }

            /* ========================= MODO: PARCIAL ========================= */
            $valorPago = min(max((float)$data['valor'], 0.01), $saldoParcela);

            // cria o pagamento parcial
            $parcela->pagamentos()->create([
                'valor'   => $valorPago,
                'pago_em' => $data['pago_em'],
                'banco'   => $data['banco'],
                'modo'    => 'parcial',
            ]);

            // rateio juros/amortização
            $origJuros = (float) $parcela->valor_juros;
            $origAmort = (float) $parcela->valor_amortizacao;

            $jurosPago     = min($valorPago, $origJuros);
            $amortPago     = max(0.0, $valorPago - $jurosPago);
            $jurosRestante = max(0.0, $origJuros - $jurosPago);
            $amortRestante = max(0.0, $origAmort - $amortPago);

            // nada restou? fecha
            if (($jurosRestante + $amortRestante) <= 0.009) {
                $parcela->valor_juros       = round($jurosPago, 2);
                $parcela->valor_amortizacao = round($amortPago, 2);
                $parcela->valor_parcela     = round($jurosPago + $amortPago, 2);
                $parcela->status            = 'paga';
                $parcela->save();
                $setValorPago($parcela);
                $this->recalcularSaldos($emprestimo);
                return back()->with('success', 'Parcela quitada.');
            }

            // parâmetros de movimento
            $destParam    = $data['destino_parcela_id'] ?? null;
            $aplicarJuros = (bool) ($data['aplicar_juros'] ?? false);

            // existe parcela futura?
            $existeFutura = $emprestimo->parcelas()
                ->where('numero', '>', $parcela->numero)
                ->exists();

            // juros extra sobre valor movido (somente no modelo de juros sobre saldo)
            $jurosExtra = 0.0;
            if ($aplicarJuros && $emprestimo->tipo_calculo === 'AMORTIZATION_ON_BALANCE') {
                $jurosExtra = $i * $amortRestante;
            }

            /* ---------- ÚLTIMA PARCELA: cria NOVA SOMANDO restante + "parcela base" ---------- */
            if (!$existeFutura) {
                $maxNumero  = (int) $emprestimo->parcelas()->max('numero');
                $novoNumero = $maxNumero + 1;

                $ultimoVenc = $emprestimo->parcelas()->reorder()->max('vencimento');
                $baseData   = $ultimoVenc ? Carbon::parse($ultimoVenc) : Carbon::parse($parcela->vencimento ?? now());
                $novoVenc   = $baseData->copy()->addMonthNoOverflow()->toDateString();

                // "Parcela base" do próximo mês:
                // - amortização base: usa a amortização padrão da linha atual
                // - juros base: usa os juros padrão da linha atual (alinha com teu exemplo 150/150)
                $baseAmort = (float) $parcela->valor_amortizacao;
                $baseJuros = (float) $parcela->valor_juros;

                // monta nova linha: (restante + base) + jurosExtra se aplicável
                $novaAmort = round($amortRestante + $baseAmort, 2);
                $novosJuros = round($jurosRestante + $baseJuros + $jurosExtra, 2);

                $emprestimo->parcelas()->create([
                    'numero'            => $novoNumero,
                    'vencimento'        => $novoVenc,
                    'valor_amortizacao' => $novaAmort,
                    'valor_juros'       => $novosJuros,
                    'valor_parcela'     => round($novaAmort + $novosJuros, 2),
                    'saldo_devedor'     => 0.0,
                    'status'            => 'aberta',
                ]);
            } else {
                /* Existem futuras: respeita a escolha do usuário */
                if (!$destParam) {
                    // mantém o restante NA MESMA parcela (fica aberta)
                    $parcela->valor_juros       = round($jurosRestante, 2);
                    $parcela->valor_amortizacao = round($amortRestante, 2);
                    $parcela->valor_parcela     = round($jurosRestante + $amortRestante, 2);
                    $parcela->status            = 'aberta';
                    $parcela->save();
                    $setValorPago($parcela);
                    $this->recalcularSaldos($emprestimo);
                    return back()->with('success', 'Pagamento parcial registrado (restante mantido na mesma parcela).');
                }

                if ($destParam === '__nova__') {
                    $maxNumero  = (int) $emprestimo->parcelas()->max('numero');
                    $novoNumero = $maxNumero + 1;

                    $ultimoVenc = $emprestimo->parcelas()->reorder()->max('vencimento');
                    $baseData   = $ultimoVenc ? Carbon::parse($ultimoVenc) : Carbon::parse($parcela->vencimento ?? now());
                    $novoVenc   = $baseData->copy()->addMonthNoOverflow()->toDateString();

                    $emprestimo->parcelas()->create([
                        'numero'            => $novoNumero,
                        'vencimento'        => $novoVenc,
                        'valor_amortizacao' => round($amortRestante, 2),
                        'valor_juros'       => round($jurosRestante + $jurosExtra, 2),
                        'valor_parcela'     => round($amortRestante + $jurosRestante + $jurosExtra, 2),
                        'saldo_devedor'     => 0.0,
                        'status'            => 'aberta',
                    ]);
                } else {
                    // mover para uma futura existente
                    $alvo = $emprestimo->parcelas()->whereKey($destParam)->first();
                    if ($alvo) {
                        $alvo->valor_amortizacao = round($alvo->valor_amortizacao + $amortRestante, 2);
                        $alvo->valor_juros       = round($alvo->valor_juros + $jurosRestante + $jurosExtra, 2);
                        $alvo->valor_parcela     = round($alvo->valor_amortizacao + $alvo->valor_juros, 2);
                        $alvo->save();
                    } else {
                        // id inválido → cria nova simples
                        $maxNumero  = (int) $emprestimo->parcelas()->max('numero');
                        $novoNumero = $maxNumero + 1;

                        $ultimoVenc = $emprestimo->parcelas()->reorder()->max('vencimento');
                        $baseData   = $ultimoVenc ? Carbon::parse($ultimoVenc) : Carbon::parse($parcela->vencimento ?? now());
                        $novoVenc   = $baseData->copy()->addMonthNoOverflow()->toDateString();

                        $emprestimo->parcelas()->create([
                            'numero'            => $novoNumero,
                            'vencimento'        => $novoVenc,
                            'valor_amortizacao' => round($amortRestante, 2),
                            'valor_juros'       => round($jurosRestante + $jurosExtra, 2),
                            'valor_parcela'     => round($amortRestante + $jurosRestante + $jurosExtra, 2),
                            'saldo_devedor'     => 0.0,
                            'status'            => 'aberta',
                        ]);
                    }
                }
            }

            // fecha a origem como "paga (parcial)" com o que foi pago
            $parcela->valor_juros       = round($jurosPago, 2);
            $parcela->valor_amortizacao = round($amortPago, 2);
            $parcela->valor_parcela     = round($jurosPago + $amortPago, 2);
            $parcela->status            = 'paga';
            $parcela->save();
            $setValorPago($parcela);

            $this->recalcularSaldos($emprestimo);
            return back()->with('success', 'Pagamento parcial registrado e restante movido corretamente.');
        });
    }

    public function edit(Parcela $parcela)
    {
        $pagamento = $parcela->pagamentos()->latest()->first();
        if (!$pagamento) {
            return back()->with('error', 'Não há pagamento registrado para esta parcela.');
        }

        return view('parcelas.edit', [
            'parcela'   => $parcela,
            'pagamento' => $pagamento,
        ]);
    }

    public function update(Request $request, Parcela $parcela)
    {
        $pagamento = $parcela->pagamentos()->latest()->first();
        if (!$pagamento) {
            return back()->with('error', 'Não há pagamento registrado para esta parcela.');
        }

        $data = $request->validate([
            'valor'      => 'required|numeric|min:0.01',
            'pago_em'    => 'required|date',
            'banco'      => 'required|in:CAIXA,C6,BRADESCO',
            'edit_class' => 'nullable|in:juros,total,parcial',
        ]);

        $approx = function (float $a, float $b, float $tol = 0.01) { return abs($a - $b) <= $tol; };

        $class = $data['edit_class'] ?? null;
        if (!$class) {
            if ($parcela->status === 'paga'
                && $approx((float)$parcela->valor_amortizacao, 0.0)
                && $approx((float)$pagamento->valor, (float)$parcela->valor_juros)) {
                $class = 'juros';
            } elseif ($parcela->status === 'paga' && (float)$parcela->valor_amortizacao > 0) {
                $class = 'total';
            }
        }

        $novoValor = round((float)$data['valor'], 2);

        // metadados comuns
        $pagamento->pago_em = $data['pago_em'];
        $pagamento->banco   = $data['banco'];

        if ($class === 'juros') {
            $pagamento->valor = $novoValor;
            $pagamento->save();

            $parcela->valor_juros       = $novoValor;
            $parcela->valor_amortizacao = 0.0;
            $parcela->valor_parcela     = $novoValor;
            $parcela->status            = 'paga';
            $parcela->save();

            $this->recalcularSaldos($parcela->emprestimo);

            return redirect()->route('emprestimos.show', $parcela->emprestimo_id)
                ->with('success', 'Pagamento (só juros) atualizado com sucesso.');
        }

        if ($class === 'total') {
            $jurosBase = max(0.0, (float)$parcela->valor_juros);
            $jurosNovo = round(min($novoValor, $jurosBase), 2);
            $amortNova = round(max(0.0, $novoValor - $jurosNovo), 2);

            $pagamento->valor = $novoValor;
            $pagamento->save();

            $parcela->valor_juros       = $jurosNovo;
            $parcela->valor_amortizacao = $amortNova;
            $parcela->valor_parcela     = $jurosNovo + $amortNova;
            $parcela->status            = 'paga';
            $parcela->save();

            $this->recalcularSaldos($parcela->emprestimo);

            return redirect()->route('emprestimos.show', $parcela->emprestimo_id)
                ->with('success', 'Pagamento (parcela total) atualizado com sucesso.');
        }

        return back()->with('error', 'Edição de valor está bloqueada para pagamentos parciais (ou não identificados).');
    }

    /**
     * Recalcula o saldo_devedor derivado: saldo = principal − soma(amortizações até a linha).
     */
    private function recalcularSaldos(Emprestimo $emprestimo): void
    {
        if (!Schema::hasColumn('parcelas', 'saldo_devedor')) return;

        $parcelas = $emprestimo->parcelas()->orderBy('numero')->get();
        $saldo = (float) $emprestimo->valor_principal;

        foreach ($parcelas as $p) {
            // grava o SALDO ANTES da amortização da linha
            $p->saldo_devedor = round($saldo, 2);
            $p->save();

            // saldo para a próxima
            $saldo = max(0.0, $saldo - (float) $p->valor_amortizacao);
        }
    }
}
