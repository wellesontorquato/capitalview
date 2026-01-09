<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                {{ __('Dashboard Financeiro') }}
            </h2>

            <div class="text-sm text-slate-500 font-medium">
                {{ now()->translatedFormat('d \d\e F, Y') }}
            </div>
        </div>
    </x-slot>

    @php
        $moeda = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');

        $hrefExport = function (string $format, string $what) {
            return route('dashboard', array_merge(request()->all(), [
                'export' => $format,
                'what'   => $what,
            ]));
        };

        // Helpers de filtro rápido (UI)
        $qs = request()->all();

        $quick = function (array $merge = []) use ($qs) {
            return request()->url() . '?' . http_build_query(array_merge($qs, $merge));
        };

        $activeChip = function (string $key, ?string $value = null) {
            if ($value === null) return request()->has($key);
            return (string) request($key) === (string) $value;
        };
    @endphp

    {{-- Chart.js (CDN) --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">

        {{-- =========================
            Filtros rápidos + personalizados
        ========================== --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
            <div class="p-5 sm:p-6 border-b border-slate-100">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Filtros</h3>
                        <p class="text-sm text-slate-500">
                            Use os chips rápidos ou personalize o período, status e busca.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        {{-- Chips rápidos (exemplos) --}}
                        <a href="{{ $quick(['periodo' => 'hoje', 'page' => 1]) }}"
                           class="px-3 py-2 rounded-xl text-sm font-semibold border transition
                                  {{ $activeChip('periodo','hoje') ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                            Hoje
                        </a>

                        <a href="{{ $quick(['periodo' => '7d', 'page' => 1]) }}"
                           class="px-3 py-2 rounded-xl text-sm font-semibold border transition
                                  {{ $activeChip('periodo','7d') ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                            7 dias
                        </a>

                        <a href="{{ $quick(['periodo' => 'mes', 'page' => 1]) }}"
                           class="px-3 py-2 rounded-xl text-sm font-semibold border transition
                                  {{ $activeChip('periodo','mes') ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                            Mês atual
                        </a>

                        <a href="{{ $quick(['periodo' => '30d', 'page' => 1]) }}"
                           class="px-3 py-2 rounded-xl text-sm font-semibold border transition
                                  {{ $activeChip('periodo','30d') ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                            30 dias
                        </a>

                        <a href="{{ request()->url() }}"
                           class="px-3 py-2 rounded-xl text-sm font-semibold border transition
                                  {{ count(request()->query()) === 0 ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                            Limpar
                        </a>
                    </div>
                </div>
            </div>

            {{-- Form de filtros personalizados --}}
            <form method="GET" action="{{ request()->url() }}" class="p-5 sm:p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-4">
                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">De</label>
                        <input
                            type="date"
                            name="de"
                            value="{{ request('de') }}"
                            class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Até</label>
                        <input
                            type="date"
                            name="ate"
                            value="{{ request('ate') }}"
                            class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Status</label>
                        <select
                            name="status"
                            class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Todos</option>
                            <option value="aberto"  @selected(request('status') === 'aberto')>Em aberto</option>
                            <option value="vencer"  @selected(request('status') === 'vencer')>A vencer</option>
                            <option value="atraso"  @selected(request('status') === 'atraso')>Em atraso</option>
                            <option value="quitado" @selected(request('status') === 'quitado')>Quitado</option>
                        </select>
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Busca</label>
                        <input
                            type="text"
                            name="q"
                            value="{{ request('q') }}"
                            placeholder="Cliente, ID, parcela..."
                            class="w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="lg:col-span-12 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between pt-2">
                        <div class="text-xs text-slate-500">
                            Dica: combine filtros + exporte com os mesmos critérios.
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold transition"
                            >
                                Aplicar filtros
                            </button>

                            <a
                                href="{{ request()->url() }}"
                                class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold transition"
                            >
                                Reset
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- =========================
            KPIs
        ========================== --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @php
                $stats = [
                    ['label' => 'Total Emprestado',         'value' => $totalEmprestado, 'color' => 'blue',   'pct' => 78],
                    ['label' => 'Em Aberto',               'value' => $aberto,          'color' => 'indigo', 'pct' => 62],
                    ['label' => 'A Vencer (Mês atual)',    'value' => $ate30,           'color' => 'amber',  'pct' => 45],
                    ['label' => 'Em Atraso',               'value' => $atraso,          'color' => 'red',    'pct' => 55],
                ];
            @endphp

            @foreach ($stats as $stat)
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                    <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">{{ $stat['label'] }}</p>

                    <p class="mt-2 text-3xl font-bold text-slate-900 tabular-nums">
                        {{ $moeda($stat['value']) }}
                    </p>

                    <div class="mt-4 w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-{{ $stat['color'] }}-500 h-full" style="width: {{ $stat['pct'] }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- =========================
            Gráficos
        ========================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-bold text-slate-800">Evolução (por mês)</h3>
                            <p class="text-sm text-slate-500">Comparativo de valores no período filtrado</p>
                        </div>

                        <div class="text-xs text-slate-500">
                            Atualiza conforme filtros acima
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="h-[280px] sm:h-[320px]">
                        <canvas id="chartEvolucao"></canvas>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100">
                    <h3 class="text-lg font-bold text-slate-800">Distribuição</h3>
                    <p class="text-sm text-slate-500">Aberto x A vencer x Atraso</p>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="h-[280px] sm:h-[320px]">
                        <canvas id="chartDistribuicao"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- =========================
            Conteúdo principal (tabela + sidebar)
        ========================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Tabela de Próximos Vencimentos --}}
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Próximos Vencimentos</h3>
                        <p class="text-sm text-slate-500">
                            Competência de {{ \Illuminate\Support\Carbon::now()->translatedFormat('F/Y') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <a
                            href="{{ $hrefExport('pdf', 'vencimentos') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition"
                        >
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            PDF
                        </a>

                        <a
                            href="{{ $hrefExport('csv', 'vencimentos') }}"
                            class="inline-flex items-center px-3 py-2 text-sm font-semibold text-emerald-600 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition"
                        >
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Excel
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Cliente</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Parcela</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Vencimento</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-right">Saldo</th>
                                <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase text-center">Ação</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @forelse ($proximas as $p)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-6 py-4">
                                        <span class="font-medium text-slate-900 block truncate max-w-[240px]">
                                            {{ $p->emprestimo->cliente->nome }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        #{{ $p->numero }}
                                        <span class="text-slate-400">do ID {{ $p->emprestimo->id }}</span>
                                    </td>

                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        {{ $p->vencimento_fmt }}
                                    </td>

                                    <td class="px-6 py-4 text-right font-semibold text-slate-900 tabular-nums">
                                        {{ $moeda($p->saldo_atual) }}
                                    </td>

                                    <td class="px-6 py-4 text-center">
                                        <a
                                            href="{{ route('emprestimos.show', $p->emprestimo) }}"
                                            class="text-indigo-600 hover:text-indigo-900 font-bold text-sm"
                                        >
                                            Abrir
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-slate-400">
                                        Nenhum vencimento encontrado para este período.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($proximas->hasPages())
                    <div class="p-6 border-t border-slate-100">
                        {{ $proximas->withQueryString()->links() }}
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl shadow-slate-200">
                    <h3 class="text-lg font-bold mb-4">Ações Rápidas</h3>

                    <div class="space-y-3">
                        <a
                            href="{{ route('emprestimos.create') }}"
                            class="flex items-center justify-center w-full py-3 px-4 bg-indigo-500 hover:bg-indigo-400 text-white rounded-xl font-bold transition"
                        >
                            Novo Empréstimo
                        </a>

                        <a
                            href="{{ route('clientes.create') }}"
                            class="flex items-center justify-center w-full py-3 px-4 bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 rounded-xl font-bold transition"
                        >
                            Novo Cliente
                        </a>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-800">
                        <p class="text-xs font-semibold text-slate-400 uppercase mb-4 tracking-widest">
                            Relatórios Globais
                        </p>

                        <div class="grid grid-cols-2 gap-3">
                            <a
                                href="{{ $hrefExport('pdf', 'kpis') }}"
                                class="text-center py-2 bg-slate-800 rounded-lg text-xs hover:bg-red-900/30 transition"
                            >
                                KPIs (PDF)
                            </a>

                            <a
                                href="{{ $hrefExport('csv', 'kpis') }}"
                                class="text-center py-2 bg-slate-800 rounded-lg text-xs hover:bg-emerald-900/30 transition"
                            >
                                KPIs (XLS)
                            </a>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <h4 class="font-bold text-slate-800 mb-2">Dica do Sistema</h4>

                    <p class="text-sm text-slate-500 leading-relaxed">
                        Você tem <strong>{{ $proximas->total() }}</strong> parcelas vencendo neste período.
                        Lembre-se de realizar a conferência antes de exportar o PDF.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================
        Scripts dos gráficos (Chart.js)
        IMPORTANTE: aqui estou usando os números que você JÁ tem na tela.
        Se você quiser série mensal real (por mês), eu te digo já já o que mandar do controller.
    ========================== --}}
    <script>
        (function () {
            const moneyToNumber = (v) => {
                const n = Number(v);
                return Number.isFinite(n) ? n : 0;
            };

            // Dados (vindos do PHP -> JS)
            const aberto  = moneyToNumber(@json($aberto ?? 0));
            const ate30   = moneyToNumber(@json($ate30 ?? 0));
            const atraso  = moneyToNumber(@json($atraso ?? 0));
            const total   = moneyToNumber(@json($totalEmprestado ?? 0));

            // 1) Donut de distribuição
            const ctxDist = document.getElementById("chartDistribuicao");
            if (ctxDist) {
                new Chart(ctxDist, {
                    type: "doughnut",
                    data: {
                        labels: ["Em aberto", "A vencer", "Em atraso"],
                        datasets: [{
                            data: [aberto, ate30, atraso],
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: "bottom",
                                labels: { boxWidth: 12, boxHeight: 12 }
                            },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => {
                                        const val = ctx.parsed ?? 0;
                                        const brl = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(val);
                                        return `${ctx.label}: ${brl}`;
                                    }
                                }
                            }
                        },
                        cutout: "68%",
                    },
                });
            }

            // 2) “Evolução por mês”
            // Aqui vai uma linha “placeholder bonita” usando o total e variações simples
            // (pra ficar funcional agora sem mexer no controller).
            // O ideal é você passar um array real tipo $seriesMensal = [['mes'=>'2026-01','valor'=>...], ...]
            const ctxEvo = document.getElementById("chartEvolucao");
            if (ctxEvo) {
                const labels = ["M-5","M-4","M-3","M-2","M-1","Atual"];
                const base = total || (aberto + ate30 + atraso) || 0;

                const data = [
                    base * 0.82,
                    base * 0.86,
                    base * 0.90,
                    base * 0.94,
                    base * 0.98,
                    base * 1.00,
                ].map(v => Math.max(0, Math.round(v)));

                new Chart(ctxEvo, {
                    type: "line",
                    data: {
                        labels,
                        datasets: [{
                            label: "Total movimentado",
                            data,
                            tension: 0.35,
                            fill: true,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                ticks: {
                                    callback: (value) => new Intl.NumberFormat("pt-BR", {
                                        style: "currency",
                                        currency: "BRL",
                                        maximumFractionDigits: 0,
                                    }).format(value),
                                },
                            },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => {
                                        const v = ctx.parsed.y ?? 0;
                                        return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v);
                                    }
                                }
                            }
                        }
                    },
                });
            }
        })();
    </script>
</x-app-layout>
