{{-- resources/views/emprestimos/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">
            Editar Empréstimo #{{ $emprestimo->id }} — {{ $emprestimo->cliente?->nome ?? '—' }}
        </h2>
    </x-slot>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="card lg:col-span-2">
            <div class="card-p">
                @if ($errors->any())
                    <div class="mb-4 p-3 rounded bg-red-100 text-red-800">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                        </ul>
                    </div>
                @endif

                {{-- AVISO se já existe cronograma --}}
                @if($emprestimo->parcelas()->exists())
                    <div class="mb-4 p-3 rounded bg-amber-50 text-amber-800 text-sm">
                        Este empréstimo já possui cronograma. Alterar valores aqui <strong>não
                        recalcula automaticamente</strong> as parcelas existentes.
                    </div>
                @endif

                <form method="POST" action="{{ route('emprestimos.update', $emprestimo) }}" class="space-y-4" id="form-emprestimo">
                    @csrf
                    @method('PATCH')

                    {{-- CLIENTE --}}
                    <div>
                        <label class="block text-sm mb-1">Cliente</label>
                        <select name="cliente_id" class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500" required>
                            @foreach($clientes as $c)
                                <option value="{{ $c->id }}" @selected(old('cliente_id', $emprestimo->cliente_id)==$c->id)>
                                    {{ $c->nome }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- VALOR E TAXA --}}
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Valor empréstimo</label>
                            <input
                                type="number" step="0.01" min="0.01" name="valor_principal" id="valor_principal"
                                class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500"
                                value="{{ old('valor_principal', $emprestimo->valor_principal) }}" required>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Taxa mensal (ex.: 0,10 = 10%)</label>
                            @php
                                // mostra com vírgula se quiser (simples): "0,10"
                                $taxaPrefill = old('taxa_mensal', str_replace('.', ',', (string)$emprestimo->taxa_periodo));
                            @endphp
                            <input
                                type="text" inputmode="decimal" name="taxa_mensal" id="taxa_mensal"
                                class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500"
                                value="{{ $taxaPrefill }}" required>
                            <p class="text-xs text-slate-500 mt-1">Pode usar vírgula ou ponto.</p>
                        </div>
                    </div>

                    {{-- TIPO DE CÁLCULO + PARCELAS + 1º VENCIMENTO --}}
                    <div class="grid sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Tipo de cálculo</label>
                            <select name="tipo_calculo" id="tipo_calculo" class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500" required>
                                <option value="FIXED_ON_PRINCIPAL" @selected(old('tipo_calculo',$emprestimo->tipo_calculo)==='FIXED_ON_PRINCIPAL')>
                                    Opção A — Juros fixos sobre o principal (parcela constante)
                                </option>
                                <option value="AMORTIZATION_ON_BALANCE" @selected(old('tipo_calculo',$emprestimo->tipo_calculo)==='AMORTIZATION_ON_BALANCE')>
                                    Opção B — Amortização + juros sobre saldo (parcela decrescente)
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Qtd. Parcelas</label>
                            <input
                                type="number" name="qtd_parcelas" id="qtd_parcelas" min="1" max="360"
                                class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500"
                                value="{{ old('qtd_parcelas', $emprestimo->qtd_parcelas) }}" required>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Primeiro vencimento</label>
                            <input
                                type="date" name="primeiro_vencimento"
                                class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500"
                                value="{{ old('primeiro_vencimento', optional($emprestimo->primeiro_vencimento)->format('Y-m-d')) }}">
                        </div>
                    </div>

                    {{-- OBSERVAÇÕES --}}
                    <div>
                        <label class="block text-sm mb-1">Observações</label>
                        <textarea
                            name="observacoes" rows="3"
                            class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500"
                            placeholder="Anotações internas do contrato...">{{ old('observacoes', $emprestimo->observacoes) }}</textarea>
                    </div>

                    {{-- AÇÕES --}}
                    <div class="flex gap-2">
                        <a href="{{ route('emprestimos.show', $emprestimo) }}" class="btn btn-ghost">Cancelar</a>
                        <button class="btn btn-primary">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- PAINEL DE DICAS/REGRAS + PRÉVIA DINÂMICA --}}
        <div class="card">
            <div class="card-p space-y-4">
                <h3 class="font-semibold text-ink-900">Como funciona</h3>
                <ul class="text-sm list-disc pl-5 space-y-2 text-slate-600">
                    <li>
                        <strong>Opção A — Juros fixos sobre o principal</strong>: parcela = (principal ÷ parcelas) + (taxa × principal).<br>
                        Ex.: 10.000 em 10x, taxa 0,10 → parcela <u>constante</u> de 2.000 (1.000 de amortização + 1.000 de juros).
                    </li>
                    <li>
                        <strong>Opção B — Amortização + juros sobre saldo</strong>: amortização fixa (principal ÷ parcelas) e juros sobre o saldo restante.<br>
                        Ex.: 1ª parcela ≈ 2.000 → última ≈ 1.100 (com taxa 0,10 e 10 parcelas).
                    </li>
                    <li>
                        <strong>Taxa mensal</strong>: informe como decimal. Ex.: 0,10 = 10% ao mês; 0,025 = 2,5% ao mês.
                    </li>
                </ul>

                <div class="border border-slate-200 rounded-xl p-3">
                    <h4 class="font-semibold mb-2">Prévia do cálculo</h4>

                    <div class="text-sm text-slate-600 mb-2">
                        <div><span class="font-medium">Tipo:</span> <span id="prev-tipo">—</span></div>
                        <div><span class="font-medium">Principal:</span> <span id="prev-pv">—</span></div>
                        <div><span class="font-medium">Taxa mensal:</span> <span id="prev-i">—</span></div>
                        <div><span class="font-medium">Parcelas:</span> <span id="prev-n">—</span></div>
                    </div>

                    <div class="grid grid-cols-1 gap-2 text-sm">
                        {{-- Opção A --}}
                        <div id="box-a" class="hidden">
                            <div class="flex justify-between">
                                <span>Parcela (fixa):</span>
                                <span class="font-semibold" id="a-parcela">—</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Juros totais:</span>
                                <span class="font-semibold" id="a-juros">—</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total pago:</span>
                                <span class="font-semibold" id="a-total">—</span>
                            </div>
                        </div>

                        {{-- Opção B --}}
                        <div id="box-b" class="hidden">
                            <div class="flex justify-between">
                                <span>1ª parcela:</span>
                                <span class="font-semibold" id="b-parcela-1">—</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Última parcela:</span>
                                <span class="font-semibold" id="b-parcela-n">—</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Juros totais (estim.):</span>
                                <span class="font-semibold" id="b-juros">—</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total pago (estim.):</span>
                                <span class="font-semibold" id="b-total">—</span>
                            </div>
                        </div>

                        <p id="prev-alert" class="text-xs text-red-600 mt-2 hidden">Preencha valores válidos para ver a prévia.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SCRIPT DE PRÉ-VISUALIZAÇÃO (mesmo do create) --}}
    <script>
        (function () {
            const elPV = document.getElementById('valor_principal');
            const elI  = document.getElementById('taxa_mensal');
            const elN  = document.getElementById('qtd_parcelas');
            const elT  = document.getElementById('tipo_calculo');

            const fmtBRL = (v) => isFinite(v) ? v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '—';
            const fmtPct = (v) => isFinite(v) ? (v*100).toLocaleString('pt-BR', { maximumFractionDigits: 6 }) + ' % a.m.' : '—';
            const show   = (el, on=true) => el.classList.toggle('hidden', !on);

            const prevTipo = document.getElementById('prev-tipo');
            const prevPV   = document.getElementById('prev-pv');
            const prevI    = document.getElementById('prev-i');
            const prevN    = document.getElementById('prev-n');

            const boxA = document.getElementById('box-a');
            const aParcela = document.getElementById('a-parcela');
            const aJuros   = document.getElementById('a-juros');
            const aTotal   = document.getElementById('a-total');

            const boxB = document.getElementById('box-b');
            const bParc1 = document.getElementById('b-parcela-1');
            const bParcN = document.getElementById('b-parcela-n');
            const bJuros = document.getElementById('b-juros');
            const bTotal = document.getElementById('b-total');

            const prevAlert = document.getElementById('prev-alert');

            function toFloat(val) {
                if (typeof val !== 'string') return parseFloat(val);
                const normalized = val.replace(/\s+/g,'').replace(',','.');
                const num = parseFloat(normalized);
                return isFinite(num) ? num : NaN;
            }

            function round2(x) {
                return Math.round((x + Number.EPSILON) * 100) / 100;
            }

            function calcPreview() {
                const pv = toFloat(elPV.value);
                const i  = toFloat(elI.value);
                const n  = parseInt(elN.value, 10);
                const tipo = elT.value;

                prevTipo.textContent = (tipo === 'FIXED_ON_PRINCIPAL')
                    ? 'Opção A — Juros fixos sobre o principal'
                    : 'Opção B — Amortização + juros sobre saldo';

                prevPV.textContent = fmtBRL(pv);
                prevI.textContent  = fmtPct(i);
                prevN.textContent  = (isFinite(n) && n>0) ? n : '—';

                const inputsValidos = isFinite(pv) && pv > 0 && isFinite(i) && i >= 0 && isFinite(n) && n > 0;

                if (!inputsValidos) {
                    show(boxA, false);
                    show(boxB, false);
                    show(prevAlert, true);
                    return;
                }

                show(prevAlert, false);

                const amort = pv / n;

                if (tipo === 'FIXED_ON_PRINCIPAL') {
                    const jurosFixos = i * pv;
                    const parcela = round2(amort + jurosFixos);
                    const jurosTotais = round2(jurosFixos * n);
                    const totalPago = round2(pv + jurosTotais);

                    aParcela.textContent = fmtBRL(parcela);
                    aJuros.textContent   = fmtBRL(jurosTotais);
                    aTotal.textContent   = fmtBRL(totalPago);

                    show(boxA, true);
                    show(boxB, false);
                } else {
                    const primeiraParcela = round2(amort + i * pv);
                    const saldoPenultimo = pv - amort * (n - 1);
                    const ultimaParcela  = round2(amort + i * saldoPenultimo);
                    const jurosTotais = round2(i * pv * (n + 1) / 2);
                    const totalPago   = round2(pv + jurosTotais);

                    bParc1.textContent = fmtBRL(primeiraParcela);
                    bParcN.textContent = fmtBRL(ultimaParcela);
                    bJuros.textContent = fmtBRL(jurosTotais);
                    bTotal.textContent = fmtBRL(totalPago);

                    show(boxA, false);
                    show(boxB, true);
                }
            }

            ['input','change'].forEach(evt => {
                elPV.addEventListener(evt, calcPreview);
                elI.addEventListener(evt, calcPreview);
                elN.addEventListener(evt, calcPreview);
                elT.addEventListener(evt, calcPreview);
            });

            // inicial
            calcPreview();
        })();
    </script>
</x-app-layout>
