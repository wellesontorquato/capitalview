<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-slate-900 text-white grid place-items-center shadow-sm">
                    {{-- Ícone (dashboard) --}}
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 13h8V3H3v10zm10 8h8V11h-8v10zM13 3h8v6h-8V3zM3 21h8v-6H3v6z"/>
                    </svg>
                </div>

                <div>
                    <h2 class="font-extrabold text-2xl text-slate-900 leading-tight">
                        {{ __('Dashboard Financeiro') }}
                    </h2>
                    <p class="text-sm text-slate-500">
                        Visão geral e relatórios do período selecionado
                    </p>
                </div>
            </div>

            <div class="hidden sm:flex items-center gap-2 text-sm text-slate-600 font-semibold">
                <span class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white border border-slate-200 shadow-sm">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3M5 11h14M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    {{ now()->translatedFormat('d \d\e F, Y') }}
                </span>
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

        // >>> IMPORTANTÍSSIMO (NOVO):
        // Esperado do controller:
        // - $totalEmprestadoPeriodo => total emprestado dentro do período filtrado
        // - $totalPagoPeriodo       => total pago dentro do período filtrado
        //
        // Se você ainda não passou essas variáveis, ele vai cair em fallback:
        $totalEmprestadoPeriodo = $totalEmprestadoPeriodo ?? ($totalEmprestado ?? 0);
        $totalPagoPeriodo       = $totalPagoPeriodo ?? 0;
    @endphp

    {{-- Chart.js (CDN) --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    {{-- Fundo mais premium, sem mexer nas lógicas --}}
    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50">
        <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">

            {{-- =========================
                Filtros rápidos + personalizados
            ========================== --}}
            <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="absolute -top-24 -right-24 h-56 w-56 rounded-full bg-indigo-100 blur-2xl opacity-70"></div>
                <div class="absolute -bottom-28 -left-24 h-56 w-56 rounded-full bg-slate-100 blur-2xl opacity-80"></div>

                <div class="relative p-5 sm:p-6 border-b border-slate-100">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h3 class="text-lg font-extrabold text-slate-900">Filtros</h3>
                            <p class="text-sm text-slate-500">
                                Use os chips rápidos ou personalize o período, status e busca.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            {{-- Chips rápidos --}}
                            @php
                                $chipBase = "px-3 py-2 rounded-2xl text-sm font-bold border transition shadow-sm";
                                $chipOn   = "bg-slate-900 text-white border-slate-900";
                                $chipOff  = "bg-white text-slate-700 border-slate-200 hover:bg-slate-50";
                            @endphp

                            <a href="{{ $quick(['periodo' => 'hoje', 'page' => 1]) }}"
                               class="{{ $chipBase }} {{ $activeChip('periodo','hoje') ? $chipOn : $chipOff }}">
                                Hoje
                            </a>

                            <a href="{{ $quick(['periodo' => '7d', 'page' => 1]) }}"
                               class="{{ $chipBase }} {{ $activeChip('periodo','7d') ? $chipOn : $chipOff }}">
                                7 dias
                            </a>

                            <a href="{{ $quick(['periodo' => 'mes', 'page' => 1]) }}"
                               class="{{ $chipBase }} {{ $activeChip('periodo','mes') ? $chipOn : $chipOff }}">
                                Mês atual
                            </a>

                            <a href="{{ $quick(['periodo' => '30d', 'page' => 1]) }}"
                               class="{{ $chipBase }} {{ $activeChip('periodo','30d') ? $chipOn : $chipOff }}">
                                30 dias
                            </a>

                            <a href="{{ request()->url() }}"
                               class="{{ $chipBase }} {{ count(request()->query()) === 0 ? $chipOn : $chipOff }}">
                                Limpar
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Form de filtros personalizados --}}
                <form method="GET" action="{{ request()->url() }}" class="relative p-5 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-4">
                        <div class="lg:col-span-3">
                            <label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">De</label>
                            <input
                                type="date"
                                name="de"
                                value="{{ request('de') }}"
                                class="w-full rounded-2xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                            />
                        </div>

                        <div class="lg:col-span-3">
                            <label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">Até</label>
                            <input
                                type="date"
                                name="ate"
                                value="{{ request('ate') }}"
                                class="w-full rounded-2xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                            />
                        </div>

                        <div class="lg:col-span-3">
                            <label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">Status</label>
                            <select
                                name="status"
                                class="w-full rounded-2xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                            >
                                <option value="">Todos</option>
                                <option value="aberto"  @selected(request('status') === 'aberto')>Em aberto</option>
                                <option value="vencer"  @selected(request('status') === 'vencer')>A vencer</option>
                                <option value="atraso"  @selected(request('status') === 'atraso')>Em atraso</option>
                                <option value="quitado" @selected(request('status') === 'quitado')>Quitado</option>
                            </select>
                        </div>

                        <div class="lg:col-span-3">
                            <label class="block text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2">Busca</label>
                            <input
                                type="text"
                                name="q"
                                value="{{ request('q') }}"
                                placeholder="Cliente, ID, parcela..."
                                class="w-full rounded-2xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                            />
                        </div>

                        <div class="lg:col-span-12 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between pt-2">
                            <div class="text-xs text-slate-500">
                                Dica: combine filtros + exporte com os mesmos critérios.
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold transition shadow-sm"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M3 4a1 1 0 011-1h16a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM4 13h16a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6a1 1 0 011-1z"/>
                                    </svg>
                                    Aplicar filtros
                                </button>

                                <a
                                    href="{{ request()->url() }}"
                                    class="inline-flex items-center justify-center px-4 py-2 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-extrabold transition shadow-sm"
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
                        ['label' => 'Total Emprestado',      'value' => $totalEmprestado, 'color' => 'blue',   'pct' => 78, 'icon' => 'M12 8c1.657 0 3-1.343 3-3S13.657 2 12 2 9 3.343 9 5s1.343 3 3 3zM5 22a7 7 0 0114 0H5z'],
                        ['label' => 'Em Aberto',             'value' => $aberto,          'color' => 'indigo', 'pct' => 62, 'icon' => 'M12 8v4l3 3'],
                        ['label' => 'A Vencer (Mês atual)',  'value' => $ate30,           'color' => 'amber',  'pct' => 45, 'icon' => 'M8 7V3m8 4V3M5 11h14'],
                        ['label' => 'Em Atraso',             'value' => $atraso,          'color' => 'red',    'pct' => 55, 'icon' => 'M12 9v4m0 4h.01'],
                    ];
                @endphp

                @foreach ($stats as $stat)
                    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-extrabold text-slate-500 uppercase tracking-wider">
                                    {{ $stat['label'] }}
                                </p>
                                <p class="mt-2 text-3xl font-extrabold text-slate-900 tabular-nums">
                                    {{ $moeda($stat['value']) }}
                                </p>
                            </div>

                            <div class="h-10 w-10 rounded-2xl bg-slate-50 border border-slate-200 grid place-items-center">
                                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stat['icon'] }}"/>
                                </svg>
                            </div>
                        </div>

                        <div class="mt-4 w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div class="bg-{{ $stat['color'] }}-500 h-full" style="width: {{ $stat['pct'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- =========================
                Gráficos
                - Agora o principal é: Total emprestado vs Total pago no período selecionado
            ========================== --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100">
                        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-extrabold text-slate-900">Emprestado x Pago</h3>
                                <p class="text-sm text-slate-500">Total no período selecionado</p>
                            </div>

                            <div class="text-xs text-slate-500">
                                Atualiza conforme filtros acima
                            </div>
                        </div>
                    </div>

                    <div class="p-4 sm:p-6">
                        <div class="h-[280px] sm:h-[320px]">
                            <canvas id="chartPeriodo"></canvas>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100">
                        <h3 class="text-lg font-extrabold text-slate-900">Distribuição</h3>
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
                {{-- Tabela --}}
                <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-extrabold text-slate-900">Próximos Vencimentos</h3>
                            <p class="text-sm text-slate-500">
                                Competência de {{ \Illuminate\Support\Carbon::now()->translatedFormat('F/Y') }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ $hrefExport('pdf', 'vencimentos') }}"
                               class="inline-flex items-center px-3 py-2 text-sm font-extrabold text-red-700 bg-red-50 rounded-2xl hover:bg-red-100 transition shadow-sm border border-red-100">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                PDF
                            </a>

                            <a href="{{ $hrefExport('csv', 'vencimentos') }}"
                               class="inline-flex items-center px-3 py-2 text-sm font-extrabold text-emerald-700 bg-emerald-50 rounded-2xl hover:bg-emerald-100 transition shadow-sm border border-emerald-100">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Excel
                            </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-6 py-4 text-xs font-extrabold text-slate-500 uppercase">Cliente</th>
                                    <th class="px-6 py-4 text-xs font-extrabold text-slate-500 uppercase">Parcela</th>
                                    <th class="px-6 py-4 text-xs font-extrabold text-slate-500 uppercase">Vencimento</th>
                                    <th class="px-6 py-4 text-xs font-extrabold text-slate-500 uppercase text-right">Saldo</th>
                                    <th class="px-6 py-4 text-xs font-extrabold text-slate-500 uppercase text-center">Ação</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                @forelse ($proximas as $p)
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="px-6 py-4">
                                            <span class="font-bold text-slate-900 block truncate max-w-[240px]">
                                                {{ $p->emprestimo->cliente->nome }}
                                            </span>
                                        </td>

                                        <td class="px-6 py-4 text-sm text-slate-600">
                                            <span class="font-bold text-slate-800">#{{ $p->numero }}</span>
                                            <span class="text-slate-400">do ID {{ $p->emprestimo->id }}</span>
                                        </td>

                                        <td class="px-6 py-4 text-sm text-slate-600">
                                            {{ $p->vencimento_fmt }}
                                        </td>

                                        <td class="px-6 py-4 text-right font-extrabold text-slate-900 tabular-nums">
                                            {{ $moeda($p->saldo_atual) }}
                                        </td>

                                        <td class="px-6 py-4 text-center">
                                            <a href="{{ route('emprestimos.show', $p->emprestimo) }}"
                                               class="inline-flex items-center justify-center px-3 py-2 rounded-2xl bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-extrabold text-sm transition">
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
                <div class="space-y-6 lg:sticky lg:top-6 h-fit">
                    <div class="bg-slate-900 rounded-3xl p-6 text-white shadow-xl shadow-slate-200 overflow-hidden relative">
                        <div class="absolute -top-20 -right-20 h-48 w-48 rounded-full bg-indigo-500/20 blur-2xl"></div>

                        <h3 class="text-lg font-extrabold mb-4 relative">Ações Rápidas</h3>

                        <div class="space-y-3 relative">
                            <a href="{{ route('emprestimos.create') }}"
                               class="flex items-center justify-center w-full py-3 px-4 bg-indigo-500 hover:bg-indigo-400 text-white rounded-2xl font-extrabold transition shadow-sm">
                                Novo Empréstimo
                            </a>

                            <a href="{{ route('clientes.create') }}"
                               class="flex items-center justify-center w-full py-3 px-4 bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 rounded-2xl font-extrabold transition shadow-sm">
                                Novo Cliente
                            </a>
                        </div>

                        <div class="mt-8 pt-6 border-t border-slate-800 relative">
                            <p class="text-xs font-extrabold text-slate-300 uppercase mb-4 tracking-widest">
                                Relatórios Globais
                            </p>

                            <div class="grid grid-cols-2 gap-3">
                                <a href="{{ $hrefExport('pdf', 'kpis') }}"
                                   class="text-center py-2 bg-slate-800 rounded-2xl text-xs hover:bg-red-900/30 transition border border-slate-700">
                                    KPIs (PDF)
                                </a>

                                <a href="{{ $hrefExport('csv', 'kpis') }}"
                                   class="text-center py-2 bg-slate-800 rounded-2xl text-xs hover:bg-emerald-900/30 transition border border-slate-700">
                                    KPIs (XLS)
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                        <h4 class="font-extrabold text-slate-900 mb-2">Dica do Sistema</h4>

                        <p class="text-sm text-slate-500 leading-relaxed">
                            Você tem <strong>{{ $proximas->total() }}</strong> parcelas vencendo neste período.
                            Lembre-se de realizar a conferência antes de exportar o PDF.
                        </p>

                        {{-- mini resumo do período (bonitinho) --}}
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-3">
                                <p class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">Emprestado</p>
                                <p class="mt-1 font-extrabold text-slate-900 tabular-nums">{{ $moeda($totalEmprestadoPeriodo) }}</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-3">
                                <p class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">Pago</p>
                                <p class="mt-1 font-extrabold text-slate-900 tabular-nums">{{ $moeda($totalPagoPeriodo) }}</p>
                            </div>
                        </div>

                        @if(empty($totalPagoPeriodo))
                            <p class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-2xl p-3">
                                Se “Pago” estiver zerado, é porque o controller ainda não está enviando <strong>$totalPagoPeriodo</strong>.
                                Me diga onde você salva os pagamentos (tabela/coluna) que eu te passo o cálculo exato.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- =========================
            Scripts dos gráficos (Chart.js)
            - Principal: Total emprestado vs Total pago no período
            - Mantém a distribuição (donut) como está
        ========================== --}}
        <script>
            (function () {
                const num = (v) => {
                    const n = Number(v);
                    return Number.isFinite(n) ? n : 0;
                };

                // Dados (PHP -> JS)
                const aberto  = num(@json($aberto ?? 0));
                const ate30   = num(@json($ate30 ?? 0));
                const atraso  = num(@json($atraso ?? 0));

                const totalEmprestadoPeriodo = num(@json($totalEmprestadoPeriodo ?? 0));
                const totalPagoPeriodo       = num(@json($totalPagoPeriodo ?? 0));

                const brl = (v, max = 2) => new Intl.NumberFormat("pt-BR", {
                    style: "currency",
                    currency: "BRL",
                    maximumFractionDigits: max,
                }).format(v);

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
                                        label: (ctx) => `${ctx.label}: ${brl(ctx.parsed ?? 0)}`
                                    }
                                }
                            },
                            cutout: "68%",
                        },
                    });
                }

                // 2) Emprestado x Pago (período)  ✅
                const ctxPeriodo = document.getElementById("chartPeriodo");
                if (ctxPeriodo) {
                    new Chart(ctxPeriodo, {
                        type: "bar",
                        data: {
                            labels: ["Período selecionado"],
                            datasets: [
                                {
                                    label: "Total emprestado",
                                    data: [totalEmprestadoPeriodo],
                                    borderWidth: 0,
                                    borderRadius: 14,
                                    barThickness: 48,
                                },
                                {
                                    label: "Total pago",
                                    data: [totalPagoPeriodo],
                                    borderWidth: 0,
                                    borderRadius: 14,
                                    barThickness: 48,
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { weight: "700" } }
                                },
                                y: {
                                    ticks: {
                                        callback: (value) => brl(value, 0),
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    position: "bottom",
                                    labels: { boxWidth: 12, boxHeight: 12 }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => `${ctx.dataset.label}: ${brl(ctx.parsed.y ?? 0)}`
                                    }
                                }
                            }
                        }
                    });
                }
            })();
        </script>
    </div>
</x-app-layout>
