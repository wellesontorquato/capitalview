<x-app-layout x-data>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">
            Empréstimo #{{ $emprestimo->id }} — {{ $emprestimo->cliente->nome }}
        </h2>
    </x-slot>

    <style>[x-cloak]{display:none!important}</style>

    <div class="p-0 sm:p-2 space-y-4">
        {{-- alerts --}}
        @if (session('success'))
            <div class="card"><div class="card-p text-emerald-700">{{ session('success') }}</div></div>
        @endif
        @if (session('error'))
            <div class="card"><div class="card-p text-red-700">{{ session('error') }}</div></div>
        @endif

        @php
            $tipoLabel = fn($t) => match($t){
                'FIXED_ON_PRINCIPAL'      => 'Opção A — Juros fixos sobre o principal',
                'AMORTIZATION_ON_BALANCE' => 'Opção B — Amortização + juros sobre saldo',
                default => '—'
            };
            $pct   = fn($x) => is_numeric($x) ? number_format($x*100, 2, ',', '.') . ' % a.m.' : '—';
            $moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

            $saldoDevedorAtual = (float) $emprestimo->parcelas->where('status','!=','paga')->sum('valor_amortizacao');
            $jurosAbertos      = (float) $emprestimo->parcelas->where('status','!=','paga')->sum('valor_juros');
            $totalHoje         = $saldoDevedorAtual + $jurosAbertos;

            $aprox = fn(float $a, float $b, float $tol = 0.01) => abs($a - $b) <= $tol;
            $badgeInfo = function($p, $parcelaAjustada, $totalPago, ?string $modo = null) use ($aprox) {
                // Preferir o modo salvo no último pagamento
                if ($modo === 'juros')   return ['JUROS',   'bg-sky-50 text-sky-700'];
                if ($modo === 'total')   return ['TOTAL',   'bg-emerald-50 text-emerald-700'];
                if ($modo === 'parcial') return ['PARCIAL', 'bg-amber-50 text-amber-700'];

                // Fallback (para pagamentos antigos que não tenham 'modo')
                if ($p->status === 'paga' && $aprox((float)$p->valor_amortizacao, 0.0) && $aprox((float)$totalPago, (float)$p->valor_juros)) {
                    return ['JUROS', 'bg-sky-50 text-sky-700'];
                }
                // Tentar identificar PARCIAL primeiro, para não cair em "TOTAL" por redefinição de valor_parcela
                if ($p->status === 'paga' && $totalPago > 0 && !$aprox((float)$totalPago, (float)$parcelaAjustada)) {
                    return ['PARCIAL', 'bg-amber-50 text-amber-700'];
                }
                if ($p->status === 'paga' && $aprox((float)$totalPago, (float)$parcelaAjustada)) {
                    return ['TOTAL', 'bg-emerald-50 text-emerald-700'];
                }
                return null;
            };

            $bancoLabel = fn($b) => match($b){
                'CAIXA'    => 'Caixa Econômica',
                'C6'       => 'C6',
                'BRADESCO' => 'Bradesco',
                default    => null
            };
        @endphp

        {{-- topo: 3 cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="stat">
                <div class="stat-title">Saldo devedor</div>
                <div class="stat-value">{{ $moeda($saldoDevedorAtual) }}</div>
            </div>
            <div class="stat">
                <div class="stat-title">Juros abertos</div>
                <div class="stat-value">{{ $moeda($jurosAbertos) }}</div>
            </div>
            <div class="stat">
                <div class="stat-title">Total a pagar hoje</div>
                <div class="stat-value">{{ $moeda($totalHoje) }}</div>
            </div>
        </div>

        {{-- linha inferior: 4 cards menores --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="stat !py-3">
                <div class="stat-title text-xs">Empréstimo</div>
                <div class="stat-value text-lg">{{ $moeda($emprestimo->valor_principal) }}</div>
            </div>
            <div class="stat !py-3">
                <div class="stat-title text-xs">Taxa mensal</div>
                <div class="stat-value text-lg">{{ $pct($emprestimo->taxa_periodo) }}</div>
            </div>
            <div class="stat !py-3">
                <div class="stat-title text-xs">Tipo de cálculo</div>
                <div class="stat-value text-sm">{{ $tipoLabel($emprestimo->tipo_calculo) }}</div>
            </div>
            <div class="stat !py-3">
                <div class="stat-title text-xs">Parcelas</div>
                <div class="stat-value text-lg">{{ $emprestimo->qtd_parcelas ?? '—' }}</div>
            </div>
        </div>

        @if($emprestimo->parcelas->count())
            <div class="card">
                <div class="card-p">
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2 mb-3">
                        <h3 class="font-semibold text-ink-900">Cronograma de Parcelas</h3>
                        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                            <a href="{{ route('emprestimos.index') }}" class="btn btn-ghost w-full sm:w-auto">Voltar</a>
                            <form id="form-quitar" method="POST" action="{{ route('emprestimos.quitar', $emprestimo) }}" class="w-full sm:w-auto">
                                @csrf
                                <button id="btn-quitar" type="button" class="btn btn-primary w-full sm:w-auto">Quitar tudo</button>
                            </form>
                        </div>
                    </div>

                    {{-- MOBILE --}}
                    <div class="grid sm:hidden gap-3">
                        @php $maxNumero = (int) $emprestimo->parcelas->max('numero'); @endphp
                        @foreach($emprestimo->parcelas as $p)
                            @php
                                $parcelaAjustada = $p->valor_parcela_ajustada ?? $p->valor_parcela;
                                $totalPago       = $p->total_pago ?? $p->valor_pago;
                                $saldoParcela    = $p->status === 'paga' ? 0 : max(0, $parcelaAjustada - $totalPago);
                                $jurosDaParcela  = (float) $p->valor_juros;

                                $ultimoPg = $p->pagamentos->sortByDesc('created_at')->first();
                                $ultimoBanco = $ultimoPg?->banco;
                                $ultimoBancoLabel = $bancoLabel($ultimoBanco);

                                $modoPg = $ultimoPg?->modo;
                                $badge  = $badgeInfo($p, $parcelaAjustada, $totalPago, $modoPg);

                                $tel = preg_replace('/\D+/', '', $emprestimo->cliente->whatsapp ?? '');
                                $wa  = $tel ? 'https://wa.me/55'.$tel.'?text='.rawurlencode(
                                    "Oi, {$emprestimo->cliente->nome}! Parcela #{$p->numero} do empréstimo {$emprestimo->id}: ".
                                    "valor ".$moeda($parcelaAjustada).", vencimento ".$p->vencimento->format('d/m/Y').". ".
                                    "Saldo atual: ".$moeda($saldoParcela)
                                ) : null;

                                $jaTemPagamento = ($totalPago ?? 0) > 0.009;

                                $prox = $emprestimo->parcelas->firstWhere('numero', $p->numero + 1);
                                $isUltima = ((int)$p->numero === $maxNumero);
                            @endphp

                            <div class="card card-p" x-data="{openPay:false, openEdit:false}">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="font-semibold flex items-center gap-2">
                                        <span>#{{ $p->numero }} · {{ $p->vencimento->format('d/m/Y') }}</span>
                                        <span class="text-[10px] rounded-full px-2 py-0.5 {{ $p->status==='paga'?'bg-emerald-50 text-emerald-700':'bg-slate-50 text-slate-600' }}">
                                            {{ strtoupper($p->status) }}
                                        </span>
                                        @if($badge)
                                            <span class="text-[10px] rounded-full px-2 py-0.5 {{ $badge[1] }}">{{ $badge[0] }}</span>
                                        @endif
                                        @if($ultimoBancoLabel)
                                            <span class="text-[10px] rounded-full px-2 py-0.5 bg-indigo-50 text-indigo-700">
                                                via {{ $ultimoBancoLabel }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-2 text-sm grid grid-cols-2 gap-x-4 gap-y-1">
                                    <div>Parcela (ajustada): <strong>{{ $moeda($parcelaAjustada) }}</strong></div>
                                    <div class="text-right">Pago: {{ $moeda($totalPago) }}</div>
                                    <div>Saldo desta parcela: <strong>{{ $moeda($saldoParcela) }}</strong></div>
                                    <div class="text-right">Saldo após (previsto): {{ $moeda($p->saldo_devedor) }}</div>
                                </div>

                                <div class="mt-3 flex flex-col sm:flex-row gap-2">
                                    {{-- WhatsApp só quando ainda não tem pagamento e não está paga --}}
                                    @if($wa && !$jaTemPagamento && $p->status !== 'paga')
                                        <a class="btn btn-ghost w-full sm:w-auto" target="_blank" rel="noopener" href="{{ $wa }}">WhatsApp</a>
                                    @endif

                                    {{-- Registrar pagamento enquanto não há pagamento e ainda não está paga --}}
                                    @if(!$jaTemPagamento && $p->status !== 'paga')
                                        <button class="btn btn-primary w-full sm:w-auto"
                                                @click="openPay=!openPay; openEdit=false"
                                                x-text="openPay ? 'Fechar' : 'Registrar pagamento'"></button>
                                    @endif

                                    {{-- Editar quando já tem pagamento (ou está paga) --}}
                                    @if($jaTemPagamento || $p->status === 'paga')
                                        <button class="btn btn-secondary w-full sm:w-auto"
                                                @click="openEdit=!openEdit; openPay=false"
                                                x-text="openEdit ? 'Fechar' : 'Editar'"></button>
                                    @endif
                                </div>

                                {{-- Form: Registrar pagamento --}}
                                @if(!$jaTemPagamento && $p->status !== 'paga')
                                <div class="mt-3" x-show="openPay" x-collapse x-cloak>
                                    <form method="POST" action="{{ route('parcelas.pagar', $p) }}"
                                          class="grid grid-cols-1 sm:grid-cols-6 gap-3"
                                          x-data="{
                                            modo: 'parcial',
                                            juros: {{ json_encode($jurosDaParcela) }},
                                            saldo: {{ json_encode($saldoParcela) }},
                                            valor: '',
                                            get isJuros(){ return this.modo==='juros' },
                                            get isParcial(){ return this.modo==='parcial' },
                                            get isTotal(){ return this.modo==='total' },
                                            setDefaults(){
                                              if(this.isJuros){ this.valor = this.juros.toFixed(2); }
                                              else if(this.isTotal){ this.valor = this.saldo.toFixed(2); }
                                              else { this.valor = ''; }
                                            }
                                          }" x-init="setDefaults()">
                                        @csrf
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs mb-1">Modo</label>
                                            <select name="modo" x-model="modo" @change="setDefaults()" class="border rounded px-3 py-2 w-full">
                                                <option value="juros">Só juros</option>
                                                <option value="parcial" selected>Parcial</option>
                                                <option value="total">Total da PARCELA</option>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-1">
                                            <label class="block text-xs mb-1">Valor</label>
                                            <input type="number" step="0.01" min="0.01" name="valor"
                                                   x-model="valor" :readonly="isJuros || isTotal"
                                                   class="border rounded px-3 py-2 w-full" required>
                                        </div>
                                        <div class="sm:col-span-1">
                                            <label class="block text-xs mb-1">Pago em</label>
                                            <input type="date" name="pago_em" class="border rounded px-3 py-2 w-full" value="{{ now()->format('Y-m-d') }}">
                                        </div>

                                        {{-- BANCO --}}
                                        <fieldset class="sm:col-span-2">
                                            <legend class="block text-xs mb-1">Banco</legend>
                                            <div class="flex flex-wrap gap-3 text-sm">
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="radio" name="banco" value="CAIXA" required>
                                                    Caixa Econômica
                                                </label>
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="radio" name="banco" value="C6">
                                                    C6
                                                </label>
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="radio" name="banco" value="BRADESCO">
                                                    Bradesco
                                                </label>
                                            </div>
                                        </fieldset>

                                        {{-- PARCIAL: mover restante/aplicar juros --}}
                                        @if($isUltima)
                                            {{-- Última parcela: não mostra select; força criar NOVA --}}
                                            <input type="hidden" name="destino_parcela_id" value="__nova__">
                                            <p class="text-xs text-slate-500 sm:col-span-6">
                                                Como esta é a última parcela, o restante será movido automaticamente para uma
                                                <strong>nova parcela</strong> no fim do cronograma.
                                            </p>
                                        @else
                                            <div class="sm:col-span-3" x-show="isParcial" x-cloak>
                                                <label class="block text-xs mb-1">Mover restante para</label>
                                                @php $prox = $emprestimo->parcelas->firstWhere('numero', $p->numero + 1); @endphp
                                                <select name="destino_parcela_id" class="border rounded px-3 py-2 w-full" :disabled="!isParcial">
                                                    <option value="">— não mover —</option>
                                                    @foreach($emprestimo->parcelas as $c)
                                                        @if($c->numero > $p->numero)
                                                            <option value="{{ $c->id }}" @selected(optional($prox)->id === $c->id)">
                                                                #{{ $c->numero }} — {{ $c->vencimento->format('d/m/Y') }}
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                    <option value="__nova__">→ Criar nova parcela</option>
                                                </select>
                                                <label class="inline-flex items-center gap-2 mt-2">
                                                    <input type="checkbox" name="aplicar_juros" value="1" checked :disabled="!isParcial">
                                                    <span class="text-sm">Aplicar juros no valor movido</span>
                                                </label>
                                            </div>
                                        @endif

                                        {{-- JUROS: aviso --}}
                                        <p class="text-xs text-slate-500 sm:col-span-6" x-show="isJuros" x-cloak>
                                            Ao pagar <strong>só os juros</strong>, a amortização desta parcela será empurrada automaticamente
                                            para uma <strong>nova parcela</strong> no fim do cronograma.
                                        </p>

                                        <div class="sm:col-span-3 text-right">
                                            <button class="btn btn-primary w-full sm:w-auto">Salvar</button>
                                        </div>
                                    </form>
                                </div>
                                @endif

                                {{-- Form: Editar pagamento (inline) --}}
                                @if($jaTemPagamento || $p->status === 'paga')
                                <div class="mt-3" x-show="openEdit" x-collapse x-cloak>
                                    @php
                                        // Detecta classes de quitação
                                        $isJurosOnly   = abs((float)$p->valor_amortizacao) <= 0.01; // quitada só em juros
                                        $isParcialPago = ($p->status === 'paga') && ($totalPago > 0.009) && !$aprox((float)$totalPago, (float)$parcelaAjustada);
                                        $editClass     = $isParcialPago ? 'parcial' : ($isJurosOnly ? 'juros' : 'total');
                                    @endphp

                                    <form method="POST" action="{{ route('parcelas.update', $p) }}"
                                        class="grid grid-cols-1 sm:grid-cols-6 gap-3">
                                        @csrf
                                        @method('PUT')

                                        <input type="hidden" name="edit_class" value="{{ $editClass }}">

                                        {{-- Valor: liberado p/ juros ou total; travado se "parcial" --}}
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs mb-1">
                                                @if($isParcialPago)
                                                    Valor pago (travado — parcial)
                                                @else
                                                    Valor pago
                                                @endif
                                            </label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                name="valor"
                                                value="{{ number_format((float)$ultimoPg->valor, 2, '.', '') }}"
                                                class="border rounded px-3 py-2 w-full {{ $isParcialPago ? 'bg-slate-50' : '' }}"
                                                @if($isParcialPago) readonly @endif
                                                required
                                            >
                                            <p class="text-[11px] text-slate-500 mt-1">
                                                @if($isParcialPago)
                                                    Pagamentos parciais ficam travados por enquanto (manteremos consistência do rateio/movimento).
                                                @else
                                                    Edição liberada para “só juros” e “parcela total”.
                                                @endif
                                            </p>
                                        </div>

                                        <div class="sm:col-span-2">
                                            <label class="block text-xs mb-1">Pago em</label>
                                            <input type="date" name="pago_em"
                                                value="{{ \Illuminate\Support\Carbon::parse($ultimoPg->pago_em)->format('Y-m-d') }}"
                                                class="border rounded px-3 py-2 w-full" required>
                                        </div>

                                        {{-- BANCO --}}
                                        <fieldset class="sm:col-span-2">
                                            <legend class="block text-xs mb-1">Banco</legend>
                                            <div class="flex flex-wrap gap-3 text-sm">
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="radio" name="banco" value="CAIXA" {{ $ultimoPg->banco==='CAIXA'?'checked':'' }} required>
                                                    Caixa Econômica
                                                </label>
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="radio" name="banco" value="C6" {{ $ultimoPg->banco==='C6'?'checked':'' }}>
                                                    C6
                                                </label>
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="radio" name="banco" value="BRADESCO" {{ $ultimoPg->banco==='BRADESCO'?'checked':'' }}>
                                                    Bradesco
                                                </label>
                                            </div>
                                        </fieldset>

                                        <div class="sm:col-span-6 text-right">
                                            <button class="btn btn-primary w-full sm:w-auto">Salvar</button>
                                        </div>
                                    </form>
                                </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- DESKTOP --}}
                    <div class="hidden sm:block">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="th">#</th>
                                    <th class="th">Vencimento</th>
                                    <th class="th">Parcela (ajustada)</th>
                                    <th class="th">Pago</th>
                                    <th class="th">Saldo da parcela</th>
                                    <th class="th">Saldo após (previsto)</th>
                                    <th class="th text-right">Ações</th>
                                </tr>
                            </thead>

                            @php $maxNumero = (int) $emprestimo->parcelas->max('numero'); @endphp
                            @foreach($emprestimo->parcelas as $p)
                                @php
                                    $parcelaAjustada = $p->valor_parcela_ajustada ?? $p->valor_parcela;
                                    $totalPago       = $p->total_pago ?? $p->valor_pago;
                                    $saldoParcela    = $p->status === 'paga' ? 0 : max(0, $parcelaAjustada - $totalPago);
                                    $jurosDaParcela  = (float) $p->valor_juros;

                                    $ultimoPg = $p->pagamentos->sortByDesc('created_at')->first();
                                    $ultimoBanco = $ultimoPg?->banco;
                                    $ultimoBancoLabel = $bancoLabel($ultimoBanco);
                                    
                                    $modoPg = $ultimoPg?->modo;
                                    $badge  = $badgeInfo($p, $parcelaAjustada, $totalPago, $modoPg);

                                    $tel = preg_replace('/\D+/', '', $emprestimo->cliente->whatsapp ?? '');
                                    $wa  = $tel ? 'https://wa.me/55'.$tel.'?text='.rawurlencode(
                                        "Oi, {$emprestimo->cliente->nome}! Parcela #{$p->numero} do empréstimo {$emprestimo->id}: ".
                                        "valor ".$moeda($parcelaAjustada).", vencimento ".$p->vencimento->format('d/m/Y').". ".
                                        "Saldo atual: ".$moeda($saldoParcela)
                                    ) : null;

                                    $jaTemPagamento = ($totalPago ?? 0) > 0.009;

                                    $prox = $emprestimo->parcelas->firstWhere('numero', $p->numero + 1);
                                    $isUltima = ((int)$p->numero === $maxNumero);
                                @endphp

                                <tbody x-data="{openPay:false, openEdit:false}">
                                    <tr>
                                        <td class="td align-middle">
                                            <div class="flex items-center gap-2">
                                                <span>{{ $p->numero }}</span>
                                                <span class="text-[10px] rounded-full px-2 py-0.5 {{ $p->status==='paga'?'bg-emerald-50 text-emerald-700':'bg-slate-50 text-slate-600' }}">
                                                    {{ strtoupper($p->status) }}
                                                </span>
                                                @if($badge)
                                                    <span class="text-[10px] rounded-full px-2 py-0.5 {{ $badge[1] }}">{{ $badge[0] }}</span>
                                                @endif
                                                @if($ultimoBancoLabel)
                                                    <span class="text-[10px] rounded-full px-2 py-0.5 bg-indigo-50 text-indigo-700">
                                                        via {{ $ultimoBancoLabel }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="td align-middle whitespace-nowrap">{{ $p->vencimento->format('d/m/Y') }}</td>
                                        <td class="td align-middle">{{ $moeda($parcelaAjustada) }}</td>
                                        <td class="td align-middle">{{ $moeda($totalPago) }}</td>
                                        <td class="td align-middle"><strong>{{ $moeda($saldoParcela) }}</strong></td>
                                        <td class="td align-middle">{{ $moeda($p->saldo_devedor) }}</td>
                                        <td class="td text-right">
                                            <div class="inline-flex gap-2">
                                                {{-- WhatsApp só enquanto não há pagamento e parcela não está paga --}}
                                                @if($wa && !$jaTemPagamento && $p->status !== 'paga')
                                                    <a class="btn btn-ghost" target="_blank" rel="noopener" href="{{ $wa }}">WhatsApp</a>
                                                @endif

                                                {{-- Registrar pagamento enquanto não há pagamento e ainda não está paga --}}
                                                @if(!$jaTemPagamento && $p->status !== 'paga')
                                                    <button class="btn btn-primary"
                                                            @click="openPay=!openPay; openEdit=false"
                                                            x-text="openPay ? 'Fechar' : 'Registrar pagamento'"></button>
                                                @endif

                                                {{-- Editar quando já tem pagamento (ou paga) --}}
                                                @if($jaTemPagamento || $p->status === 'paga')
                                                    <button class="btn btn-secondary"
                                                            @click="openEdit=!openEdit; openPay=false"
                                                            x-text="openEdit ? 'Fechar' : 'Editar'"></button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>

                                    {{-- Form: Registrar pagamento --}}
                                    @if(!$jaTemPagamento && $p->status !== 'paga')
                                    <tr x-show="openPay" x-collapse x-cloak>
                                        <td class="td" colspan="7">
                                            <form method="POST" action="{{ route('parcelas.pagar', $p) }}"
                                                  class="grid grid-cols-6 gap-3"
                                                  x-data="{
                                                    modo: 'parcial',
                                                    juros: {{ json_encode($jurosDaParcela) }},
                                                    saldo: {{ json_encode($saldoParcela) }},
                                                    valor: '',
                                                    get isJuros(){ return this.modo==='juros' },
                                                    get isParcial(){ return this.modo==='parcial' },
                                                    get isTotal(){ return this.modo==='total' },
                                                    setDefaults(){
                                                      if(this.isJuros){ this.valor = this.juros.toFixed(2); }
                                                      else if(this.isTotal){ this.valor = this.saldo.toFixed(2); }
                                                      else { this.valor = ''; }
                                                    }
                                                  }" x-init="setDefaults()">
                                                @csrf
                                                <div class="col-span-2">
                                                    <label class="block text-xs mb-1">Modo</label>
                                                    <select name="modo" x-model="modo" @change="setDefaults()" class="border rounded px-3 py-2 w-full">
                                                        <option value="juros">Só juros</option>
                                                        <option value="parcial" selected>Parcial</option>
                                                        <option value="total">Total da PARCELA</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs mb-1">Valor</label>
                                                    <input type="number" step="0.01" min="0.01" name="valor"
                                                           x-model="valor" :readonly="isJuros || isTotal"
                                                           class="border rounded px-3 py-2 w-full" required>
                                                </div>
                                                <div>
                                                    <label class="block text-xs mb-1">Pago em</label>
                                                    <input type="date" name="pago_em" class="border rounded px-3 py-2 w-full" value="{{ now()->format('Y-m-d') }}">
                                                </div>

                                                {{-- BANCO --}}
                                                <fieldset class="col-span-2">
                                                    <legend class="block text-xs mb-1">Banco do pagamento</legend>
                                                    <div class="flex flex-wrap gap-3 text-sm">
                                                        <label class="inline-flex items-center gap-2">
                                                            <input type="radio" name="banco" value="CAIXA" required>
                                                            Caixa Econômica
                                                        </label>
                                                        <label class="inline-flex items-center gap-2">
                                                            <input type="radio" name="banco" value="C6">
                                                            C6
                                                        </label>
                                                        <label class="inline-flex items-center gap-2">
                                                            <input type="radio" name="banco" value="BRADESCO">
                                                            Bradesco
                                                        </label>
                                                    </div>
                                                </fieldset>

                                                {{-- PARCIAL: mover restante/aplicar juros --}}
                                                @if($isUltima)
                                                    {{-- Última parcela: não mostra select; força criar NOVA --}}
                                                    <input type="hidden" name="destino_parcela_id" value="__nova__">
                                                    <p class="text-xs text-slate-500 col-span-6">
                                                        Como esta é a última parcela, o restante será movido automaticamente para uma
                                                        <strong>nova parcela</strong> no fim do cronograma.
                                                    </p>
                                                @else
                                                    <div class="col-span-2" x-show="isParcial" x-cloak>
                                                        <label class="block text-xs mb-1">Mover restante para</label>
                                                        @php $prox = $emprestimo->parcelas->firstWhere('numero', $p->numero + 1); @endphp
                                                        <select name="destino_parcela_id" class="border rounded px-3 py-2 w-full" :disabled="!isParcial">
                                                            <option value="">— não mover —</option>
                                                            @foreach($emprestimo->parcelas as $c)
                                                                @if($c->numero > $p->numero)
                                                                    <option value="{{ $c->id }}" @selected(optional($prox)->id === $c->id)">
                                                                        #{{ $c->numero }} — {{ $c->vencimento->format('d/m/Y') }}
                                                                    </option>
                                                                @endif
                                                            @endforeach
                                                            <option value="__nova__">→ Criar nova parcela</option>
                                                        </select>
                                                        <label class="inline-flex items-center gap-2 mt-2">
                                                            <input type="checkbox" name="aplicar_juros" value="1" checked :disabled="!isParcial">
                                                            <span class="text-sm">Aplicar juros no valor movido</span>
                                                        </label>
                                                    </div>
                                                @endif

                                                {{-- JUROS: aviso --}}
                                                <p class="text-xs text-slate-500 col-span-6" x-show="isJuros" x-cloak>
                                                    Ao pagar <strong>só os juros</strong>, a amortização desta parcela será empurrada automaticamente
                                                    para uma <strong>nova parcela</strong> no fim do cronograma.
                                                </p>

                                                <div class="col-span-3 text-right">
                                                    <button class="btn btn-primary">Salvar</button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                    @endif

                                    {{-- Form: Editar pagamento (inline) --}}
                                    @if($jaTemPagamento || $p->status === 'paga')
                                    <tr x-show="openEdit" x-collapse x-cloak>
                                        <td class="td" colspan="7">
                                            @php
                                                $isJurosOnly   = abs((float)$p->valor_amortizacao) <= 0.01;
                                                $isParcialPago = ($p->status === 'paga') && ($totalPago > 0.009) && !$aprox((float)$totalPago, (float)$parcelaAjustada);
                                                $editClass     = $isParcialPago ? 'parcial' : ($isJurosOnly ? 'juros' : 'total');
                                            @endphp

                                            <form method="POST" action="{{ route('parcelas.update', $p) }}"
                                                class="grid grid-cols-6 gap-3">
                                                @csrf
                                                @method('PUT')

                                                <input type="hidden" name="edit_class" value="{{ $editClass }}">

                                                {{-- Valor: liberado p/ juros ou total; travado se "parcial" --}}
                                                <div class="col-span-2">
                                                    <label class="block text-xs mb-1">
                                                        @if($isParcialPago)
                                                            Valor pago (travado — parcial)
                                                        @else
                                                            Valor pago
                                                        @endif
                                                    </label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        name="valor"
                                                        value="{{ number_format((float)$ultimoPg->valor, 2, '.', '') }}"
                                                        class="border rounded px-3 py-2 w-full {{ $isParcialPago ? 'bg-slate-50' : '' }}"
                                                        @if($isParcialPago) readonly @endif
                                                        required
                                                    >
                                                    <p class="text-[11px] text-slate-500 mt-1">
                                                        @if($isParcialPago)
                                                            Pagamentos parciais ficam travados por enquanto (manteremos consistência do rateio/movimento).
                                                        @else
                                                            Edição liberada para “só juros” e “parcela total”.
                                                        @endif
                                                    </p>
                                                </div>

                                                <div>
                                                    <label class="block text-xs mb-1">Pago em</label>
                                                    <input type="date" name="pago_em"
                                                        value="{{ \Illuminate\Support\Carbon::parse($ultimoPg->pago_em)->format('Y-m-d') }}"
                                                        class="border rounded px-3 py-2 w-full" required>
                                                </div>

                                                {{-- BANCO --}}
                                                <fieldset class="col-span-2">
                                                    <legend class="block text-xs mb-1">Banco</legend>
                                                    <div class="flex flex-wrap gap-3 text-sm">
                                                        <label class="inline-flex items-center gap-2">
                                                            <input type="radio" name="banco" value="CAIXA" {{ $ultimoPg->banco==='CAIXA'?'checked':'' }} required>
                                                            Caixa Econômica
                                                        </label>
                                                        <label class="inline-flex items-center gap-2">
                                                            <input type="radio" name="banco" value="C6" {{ $ultimoPg->banco==='C6'?'checked':'' }}>
                                                            C6
                                                        </label>
                                                        <label class="inline-flex items-center gap-2">
                                                            <input type="radio" name="banco" value="BRADESCO" {{ $ultimoPg->banco==='BRADESCO'?'checked':'' }}>
                                                            Bradesco
                                                        </label>
                                                    </div>
                                                </fieldset>

                                                <div class="col-span-3 text-right">
                                                    <button class="btn btn-primary">Salvar</button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                    @endif
                                </tbody>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-p">
                    <p class="mb-3">Sem cronograma para este empréstimo.</p>
                    <form method="POST" action="{{ route('emprestimos.gerar', $emprestimo) }}" class="grid sm:grid-cols-3 gap-3">
                        @csrf
                        <div>
                            <label class="block text-sm mb-1">Qtd. parcelas</label>
                            <input type="number" name="qtd_parcelas" min="1" class="w-full rounded-xl border-slate-300"
                                   value="{{ old('qtd_parcelas', $emprestimo->qtd_parcelas) }}" required>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Primeiro vencimento</label>
                            <input type="date" name="primeiro_vencimento" class="w-full rounded-xl border-slate-300"
                                   value="{{ old('primeiro_vencimento', optional($emprestimo->primeiro_vencimento)->format('Y-m-d')) }}" required>
                        </div>
                        <div class="flex items-end">
                            <button class="btn btn-primary w-full">Gerar cronograma</button>
                        </div>
                    </form>
                    <p class="text-sm text-slate-500 mt-2">
                        O cronograma seguirá o tipo selecionado no empréstimo:
                        <strong>Opção A</strong> (parcela constante com juros fixos sobre o principal) ou
                        <strong>Opção B</strong> (parcela decrescente com juros sobre saldo).
                    </p>
                </div>
            </div>
        @endif
    </div>

    {{-- SweetAlert2 (preview quitação) --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    (function() {
      const btn  = document.getElementById('btn-quitar');
      const form = document.getElementById('form-quitar');
      if (!btn || !form) return;

      // hidden inputs para enviar decisão
      function ensureHidden(name, def='') {
        let el = form.querySelector(`input[name="${name}"]`);
        if (!el) {
          el = document.createElement('input');
          el.type = 'hidden';
          el.name = name;
          el.value = def;
          form.appendChild(el);
        }
        return el;
      }
      const hModo   = ensureHidden('modo');
      const hDesc   = ensureHidden('desconto_percentual');
      const hPagoEm = ensureHidden('pago_em', new Date().toISOString().slice(0,10));
      const hBanco  = ensureHidden('banco');

      const fmt = v => Number(v || 0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});

      btn.addEventListener('click', async () => {
        try {
          const res = await fetch('{{ route("emprestimos.quitacaoPreview", $emprestimo) }}', {
            headers: {'X-Requested-With':'XMLHttpRequest'}
          });
          if (!res.ok) throw new Error('Falha ao calcular');
          const q = await res.json();

          let desconto = 0; // %
          let modo = 'ACORDADO';

          const html = `
            <style>
              .sw-box{display:flex;flex-direction:column;gap:.75rem}
              .sw-card{padding:.85rem;border-radius:.75rem;border:1px solid #e5e7eb}
              .sw-strong{font-weight:600}
              .sw-small{font-size:12px;color:#6b7280}
              .sw-input{width:100%;padding:.5rem .6rem;border:1px solid #e5e7eb;border-radius:.5rem}
              .sw-row{display:grid;grid-template-columns:1fr;gap:.5rem;align-items:center;margin:.35rem 0}
              .sw-row > span:last-child{text-align:right}
              .sw-bank{display:grid;grid-template-columns:1fr;gap:.5rem;margin-top:.5rem}
              @media(min-width:480px){
                .sw-row{grid-template-columns:1fr auto}
                .sw-bank{grid-template-columns:repeat(3,1fr)}
              }
              .sw-actions{display:flex;gap:.5rem;align-items:center}
              .sw-card input[type="radio"]{margin-right:.5rem;transform:translateY(1px)}
            </style>

            <div class="sw-box">

              <label class="sw-card">
                <input type="radio" name="sw-modo" value="ACORDADO" checked>
                <span class="sw-strong">Quitar pelo acordado (restante contratual)</span>
                <div class="sw-row">
                  <span class="sw-small">Total das parcelas em aberto</span>
                  <span class="sw-strong" id="v-acordado">${fmt(q.restante_contratual)}</span>
                </div>
              </label>

              <label class="sw-card">
                <input type="radio" name="sw-modo" value="AMORTIZACAO">
                <span class="sw-strong">Quitar por amortização agora</span>
                <div class="sw-row"><span class="sw-small">Empréstimo restante</span><span class="sw-strong">${fmt(q.principal_restante)}</span></div>
                <div class="sw-row"><span class="sw-small">Juros do mês</span><span class="sw-strong">${fmt(q.juros_mes)}</span></div>
                <div class="sw-row"><span class="sw-small">Total</span><span class="sw-strong" id="v-amort">${fmt(q.total_amortizar_agora)}</span></div>
              </label>

              <label class="sw-card" id="sw-desconto-card">
                <input type="radio" name="sw-modo" value="DESCONTO">
                <span class="sw-strong">Quitar com desconto</span>
                <div class="sw-row">
                  <span class="sw-small">Base (restante contratual)</span>
                  <span class="sw-strong">${fmt(q.restante_contratual)}</span>
                </div>
                <div class="sw-row">
                  <span class="sw-small">Desconto (%)</span>
                  <input id="sw-desc" class="sw-input" type="number" min="0" max="100" step="0.1" value="0">
                </div>
                <div class="sw-row">
                  <span class="sw-small">Total com desconto</span>
                  <span class="sw-strong" id="v-desc">${fmt(q.restante_contratual)}</span>
                </div>
              </label>

              <div class="sw-card">
                <div class="sw-row">
                  <span class="sw-small">Pago em</span>
                  <input id="sw-pagoem" class="sw-input" type="date" value="{{ now()->format('Y-m-d') }}">
                </div>
                <div class="sw-row" style="margin-top:.35rem">
                  <span class="sw-small">Banco</span>
                  <div class="sw-bank">
                    <label class="sw-actions"><input type="radio" name="sw-banco" value="CAIXA"> Caixa Econômica</label>
                    <label class="sw-actions"><input type="radio" name="sw-banco" value="C6"> C6</label>
                    <label class="sw-actions"><input type="radio" name="sw-banco" value="BRADESCO"> Bradesco</label>
                  </div>
                </div>
              </div>

            </div>
          `;

          const swal = await Swal.fire({
            title: 'Quitar empréstimo',
            html,
            width: 600,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Confirmar quitação',
            cancelButtonText: 'Cancelar',
            didOpen: () => {
              const c = Swal.getHtmlContainer();
              const rads = c.querySelectorAll('input[name="sw-modo"]');
              const desc = c.querySelector('#sw-desc');
              const outDesc = c.querySelector('#v-desc');
              const pagoEmEl = c.querySelector('#sw-pagoem');
              const bancos = c.querySelectorAll('input[name="sw-banco"]');

              function recalc() {
                const base = Number(q.restante_contratual);
                const pct  = Number(desc?.value || 0);
                const val  = Math.max(0, base * (1 - pct/100));
                if (outDesc) outDesc.textContent = fmt(val);
              }
              desc?.addEventListener('input', recalc);
              recalc();

              rads.forEach(r => r.addEventListener('change', () => { modo = r.value; }));
              pagoEmEl?.addEventListener('change', () => { hPagoEm.value = pagoEmEl.value; });

              // atualiza hidden banco ao selecionar
              bancos.forEach(b => b.addEventListener('change', (ev) => {
                hBanco.value = ev.target.value;
              }));
            },
            preConfirm: () => {
              const c = Swal.getHtmlContainer();
              const checkedModo  = c.querySelector('input[name="sw-modo"]:checked');
              const checkedBanco = c.querySelector('input[name="sw-banco"]:checked');
              const descEl       = c.querySelector('#sw-desc');
              const pagoem       = c.querySelector('#sw-pagoem');

              if (!checkedBanco) {
                Swal.showValidationMessage('Selecione o banco do pagamento');
                return false;
              }

              modo = checkedModo ? checkedModo.value : 'ACORDADO';
              desconto = modo === 'DESCONTO' ? Number(descEl?.value || 0) : 0;

              hModo.value   = modo;
              hDesc.value   = desconto.toString();
              hPagoEm.value = pagoem?.value || hPagoEm.value;
              hBanco.value  = checkedBanco.value;
            }
          });

          if (swal.isConfirmed) {
            form.submit();
          }
        } catch (e) {
          console.error(e);
          Swal.fire('Erro', 'Não foi possível montar as opções de quitação.', 'error');
        }
      });
    })();
    </script>
</x-app-layout>
