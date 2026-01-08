<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Novo Empréstimo</h2></x-slot>

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

                <form method="POST" action="{{ route('emprestimos.store') }}" class="space-y-4" id="form-emprestimo">
                    @csrf

                    {{-- CLIENTE --}}
                    <div>
                        <label class="block text-sm mb-1">Cliente</label>
                        <select name="cliente_id" class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500" required>
                            @foreach($clientes as $c)
                                <option value="{{ $c->id }}" @selected(old('cliente_id', request('cliente'))==$c->id)>{{ $c->nome }}</option>
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
                                value="{{ old('valor_principal') }}" placeholder="Ex.: 10000.00" required>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Taxa mensal (ex.: 0,10 = 10%)</label>
                            <input
                                type="text" inputmode="decimal" name="taxa_mensal" id="taxa_mensal"
                                class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500"
                                value="{{ old('taxa_mensal') }}" placeholder="Ex.: 0,10" required>
                            <p class="text-xs text-slate-500 mt-1">Pode usar vírgula ou ponto.</p>
                        </div>
                    </div>

                    {{-- TIPO DE CÁLCULO + PARCELAS + 1º VENCIMENTO --}}
                    <div class="grid sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm mb-1">Tipo de cálculo</label>
                            <select name="tipo_calculo" id="tipo_calculo" class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500" required>
                                <option value="FIXED_ON_PRINCIPAL" @selected(old('tipo_calculo')==='FIXED_ON_PRINCIPAL')>
                                    Opção A — Juros fixos sobre o principal (parcela constante)
                                </option>
                                <option value="AMORTIZATION_ON_BALANCE" @selected(old('tipo_calculo')==='AMORTIZATION_ON_BALANCE')>
                                    Opção B — Amortização + juros sobre saldo (parcela decrescente)
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Qtd. Parcelas</label>
                            <input
                                type="number" name="qtd_parcelas" id="qtd_parcelas" min="1" max="360"
                                class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500"
                                value="{{ old('qtd_parcelas') }}" placeholder="Ex.: 10" required>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Primeiro vencimento</label>
                            <input
                                type="date" name="primeiro_vencimento" id="primeiro_vencimento"
                                class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500"
                                value="{{ old('primeiro_vencimento') }}">
                        </div>
                    </div>

                    {{-- 1ª PARCELA: PROPORCIONAL vs INTEGRAL --}}
                    <fieldset class="border border-slate-200 rounded-xl p-3">
                        <legend class="text-sm font-semibold px-2">1ª parcela</legend>
                        <div class="grid sm:grid-cols-2 gap-3 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" name="primeira_proporcional" value="1"
                                       @checked(old('primeira_proporcional', '1')==='1')>
                                1ª parcela proporcional (se &lt; 30 dias)
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" name="primeira_proporcional" value="0"
                                       @checked(old('primeira_proporcional')==='0')>
                                1ª parcela integral
                            </label>
                        </div>
                        <p class="text-xs text-slate-500 mt-2" id="hint-proporcional">
                            Se proporcional, os <strong>juros</strong> da 1ª parcela serão ajustados por <code>dias/30</code>
                            com base na diferença entre hoje e o primeiro vencimento. A <strong>amortização</strong> permanece igual.
                        </p>
                    </fieldset>

                    {{-- OBSERVAÇÕES --}}
                    <div>
                        <label class="block text-sm mb-1">Observações</label>
                        <textarea
                            name="observacoes" rows="3"
                            class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500"
                            placeholder="Anotações internas do contrato...">{{ old('observacoes') }}</textarea>
                    </div>

                    {{-- AÇÕES --}}
                    <div class="flex gap-2">
                        <a href="{{ route('emprestimos.index') }}" class="btn btn-ghost">Cancelar</a>
                        <button class="btn btn-primary">Salvar</button>
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
                        <div><span class="font-medium">Empréstimo:</span> <span id="prev-pv">—</span></div>
                        <div><span class="font-medium">Taxa mensal:</span> <span id="prev-i">—</span></div>
                        <div><span class="font-medium">Parcelas:</span> <span id="prev-n">—</span></div>
                    </div>

                    <div class="grid grid-cols-1 gap-2 text-sm">
                        {{-- Opção A --}}
                        <div id="box-a" class="hidden">
                            <div class="flex justify-between">
                                <span>Parcela (fixa)*:</span>
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
                            <p class="text-xs text-slate-500" id="a-observacao" class="hidden"></p>
                        </div>

                        {{-- Opção B --}}
                        <div id="box-b" class="hidden">
                            <div class="flex justify-between">
                                <span>1ª parcela:</span>
                                <span class="font-semibold" id="b-parcela-1">—</span>
                            </div>

                            {{-- ✅ NOVO: demais parcelas "típica" (mês 2) --}}
                            <div class="flex justify-between hidden" id="row-b-demais">
                                <span>Demais parcelas:</span>
                                <span class="font-semibold" id="b-parcela-demais">—</span>
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

    {{-- SCRIPT DE PRÉ-VISUALIZAÇÃO --}}
    <script>
        (function () {
            const elPV  = document.getElementById('valor_principal');
            const elI   = document.getElementById('taxa_mensal');
            const elN   = document.getElementById('qtd_parcelas');
            const elT   = document.getElementById('tipo_calculo');
            const elDue = document.getElementById('primeiro_vencimento');
            const elRadioProp = document.querySelector('input[name="primeira_proporcional"][value="1"]');
            const elRadioInt  = document.querySelector('input[name="primeira_proporcional"][value="0"]');

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
            const aObs     = document.getElementById('a-observacao');

            const boxB = document.getElementById('box-b');
            const bParc1 = document.getElementById('b-parcela-1');
            const bParcN = document.getElementById('b-parcela-n');
            const bParcDemais = document.getElementById('b-parcela-demais');
            const rowBDemais = document.getElementById('row-b-demais');
            const bJuros = document.getElementById('b-juros');
            const bTotal = document.getElementById('b-total');

            const prevAlert = document.getElementById('prev-alert');

            function toFloat(val) {
                if (typeof val !== 'string') return parseFloat(val);
                const normalized = val.replace(/\s+/g,'').replace(',','.');
                const num = parseFloat(normalized);
                return isFinite(num) ? num : NaN;
            }

            function round2(x) { return Math.round((x + Number.EPSILON) * 100) / 100; }

            function diffDaysFromToday(dateStr) {
                if (!dateStr) return NaN;
                const due = new Date(dateStr + 'T12:00:00');
                const now = new Date();
                const start = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 12, 0, 0);
                const ms = due - start;
                return Math.floor(ms / 86400000);
            }

            function firstInterestRatio() {
                const proporcional = elRadioProp?.checked ?? true;
                const dias = diffDaysFromToday(elDue?.value);
                if (!proporcional || !isFinite(dias)) return 1;
                if (dias > 0 && dias < 30) return dias / 30;
                return 1;
            }

            function calcPreview() {
                const pv = toFloat(elPV.value);
                const i  = toFloat(elI.value);
                const n  = parseInt(elN.value, 10);
                const tipo = elT.value;
                const ratio = firstInterestRatio();

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
                    const jurosMes = i * pv;

                    const parcelaNormal = round2(amort + jurosMes);
                    const jurosPrimeiro = round2(jurosMes * ratio);
                    const parcelaPrimeira = round2(amort + jurosPrimeiro);

                    const jurosTotais = round2(jurosPrimeiro + jurosMes * (n - 1));
                    const totalPago   = round2(pv + jurosTotais);

                    if (ratio < 1) {
                        aParcela.textContent = `1ª: ${fmtBRL(parcelaPrimeira)} | demais: ${fmtBRL(parcelaNormal)}`;
                        aObs.textContent = 'Obs.: 1ª parcela proporcional por dias/30; demais permanecem fixas.';
                    } else {
                        aParcela.textContent = fmtBRL(parcelaNormal);
                        aObs.textContent = '';
                    }

                    aJuros.textContent   = fmtBRL(jurosTotais);
                    aTotal.textContent   = fmtBRL(totalPago);

                    show(boxA, true);
                    show(boxB, false);
                } else {
                    // Opção B: amortização fixa + juros sobre saldo
                    const juros1 = i * pv * ratio;
                    const primeiraParcela = round2(amort + juros1);

                    // ✅ "Demais parcelas" típica = parcela do mês 2:
                    // amort + juros sobre (pv - amort). Não usa ratio.
                    const saldoAposPrimeiraAmort = pv - amort;
                    const demaisParcelaTipica = round2(amort + i * saldoAposPrimeiraAmort);

                    const saldoPenultimo = pv - amort * (n - 1);
                    const ultimaParcela  = round2(amort + i * saldoPenultimo);

                    const jurosTotaisBase = i * pv * (n + 1) / 2;
                    const jurosTotais = round2(jurosTotaisBase - (1 - ratio) * i * pv);
                    const totalPago   = round2(pv + jurosTotais);

                    bParc1.textContent = fmtBRL(primeiraParcela);
                    bParcN.textContent = fmtBRL(ultimaParcela);

                    // mostra "Demais" só quando existe mês 2 (n >= 3)
                    show(rowBDemais, isFinite(n) && n >= 3);
                    if (isFinite(n) && n >= 3) {
                        bParcDemais.textContent = fmtBRL(demaisParcelaTipica);
                    }

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
                elDue.addEventListener(evt, calcPreview);
                elRadioProp.addEventListener(evt, calcPreview);
                elRadioInt.addEventListener(evt, calcPreview);
            });

            calcPreview();
        })();
    </script>
</x-app-layout>
