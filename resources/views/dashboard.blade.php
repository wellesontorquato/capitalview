<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-900 leading-tight">
                    {{ __('Dashboard Financeiro') }}
                </h2>
                <p class="text-sm text-slate-500 font-medium">Bem-vindo de volta, acompanhe seus indicadores.</p>
            </div>

            <div class="inline-flex items-center px-4 py-2 bg-white border border-slate-200 rounded-2xl shadow-sm text-sm text-slate-600 font-bold">
                <svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
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

        $qs = request()->all();
        $quick = fn(array $merge = []) => request()->url() . '?' . http_build_query(array_merge($qs, $merge));
        $activeChip = fn(string $key, ?string $value = null) => $value === null ? request()->has($key) : (string) request($key) === (string) $value;
    @endphp

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">

        {{-- Filtros Superiores --}}
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 bg-slate-50/50 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-600 rounded-lg shadow-indigo-200 shadow-lg">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800">Filtrar Período</h3>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach(['hoje' => 'Hoje', '7d' => '7 dias', 'mes' => 'Mês atual', '30d' => '30 dias'] as $key => $label)
                        <a href="{{ $quick(['periodo' => $key, 'page' => 1]) }}"
                           class="px-4 py-2 rounded-xl text-xs font-bold transition-all
                                  {{ $activeChip('periodo', $key) ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                    <a href="{{ request()->url() }}" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-200 text-slate-700 hover:bg-slate-300 transition">Limpar</a>
                </div>
            </div>

            <form method="GET" action="{{ request()->url() }}" class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="periodo" value="{{ request('periodo') }}">
                <div>
                    <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1 block">Data Início</label>
                    <input type="date" name="de" value="{{ request('de') }}" class="w-full rounded-xl border-slate-200 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1 block">Data Fim</label>
                    <input type="date" name="ate" value="{{ request('ate') }}" class="w-full rounded-xl border-slate-200 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1 block">Status</label>
                    <select name="status" class="w-full rounded-xl border-slate-200 text-sm focus:ring-indigo-500">
                        <option value="">Todos os status</option>
                        <option value="aberto" @selected(request('status') === 'aberto')>Em aberto</option>
                        <option value="atraso" @selected(request('status') === 'atraso')>Em atraso</option>
                        <option value="quitado" @selected(request('status') === 'quitado')>Quitado</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full py-2.5 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-slate-800 transition">Aplicar Filtros</button>
                </div>
            </form>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @php
                $cards = [
                    ['label' => 'Total Emprestado', 'value' => $totalEmprestado, 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'blue'],
                    ['label' => 'Total em Aberto', 'value' => $aberto, 'icon' => 'M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A10.003 10.003 0 004.24 12.07a10.003 10.003 0 008.259-8.664m-4.69 4.056l.003-.01', 'color' => 'indigo'],
                    ['label' => 'A Vencer (30d)', 'value' => $ate30, 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'color' => 'amber'],
                    ['label' => 'Total em Atraso', 'value' => $atraso, 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 'color' => 'red'],
                ];
            @endphp
            @foreach($cards as $c)
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm relative overflow-hidden group">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-{{ $c['color'] }}-50 text-{{ $c['color'] }}-600 rounded-xl group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $c['icon'] }}"/></svg>
                        </div>
                    </div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $c['label'] }}</p>
                    <h4 class="text-2xl font-black text-slate-900 mt-1 tabular-nums">{{ $moeda($c['value']) }}</h4>
                </div>
            @endforeach
        </div>

        {{-- Gráficos --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Emprestado vs Pago</h3>
                        <p class="text-sm text-slate-500">Comparativo financeiro do período</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs font-bold">
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-indigo-500 rounded-full"></span> Emprestado</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-emerald-400 rounded-full"></span> Pago</span>
                    </div>
                </div>
                <div class="h-[350px]">
                    <canvas id="chartPrincipal"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col">
                <h3 class="text-lg font-bold text-slate-800 mb-1">Status da Carteira</h3>
                <p class="text-sm text-slate-500 mb-6">Distribuição de valores totais</p>
                <div class="flex-1 relative flex items-center justify-center">
                    <canvas id="chartRosca"></canvas>
                </div>
            </div>
        </div>

        {{-- Tabela e Sidebar --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">Próximos Vencimentos</h3>
                    <div class="flex gap-2">
                        <a href="{{ $hrefExport('pdf','vencimentos') }}" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></a>
                        <a href="{{ $hrefExport('csv','vencimentos') }}" class="p-2 text-emerald-600 hover:bg-emerald-50 rounded-lg transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></a>
                    </div>
                </div>
                <div class="overflow-x-auto text-sm">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 text-slate-400 font-extrabold uppercase tracking-widest text-[10px]">
                            <tr>
                                <th class="px-6 py-4">Cliente</th>
                                <th class="px-6 py-4 text-center">Parcela</th>
                                <th class="px-6 py-4 text-center">Vencimento</th>
                                <th class="px-6 py-4 text-right">Saldo</th>
                                <th class="px-6 py-4 text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @foreach ($proximas as $p)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4 font-bold text-slate-900">{{ $p->emprestimo->cliente->nome }}</td>
                                    <td class="px-6 py-4 text-center text-slate-500">#{{ $p->numero }}</td>
                                    <td class="px-6 py-4 text-center italic">{{ $p->vencimento_fmt }}</td>
                                    <td class="px-6 py-4 text-right tabular-nums">{{ $moeda($p->saldo_atual) }}</td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="{{ route('emprestimos.show', $p->emprestimo) }}" class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition">Ver</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-indigo-700 rounded-3xl p-8 text-white shadow-xl shadow-indigo-100 relative overflow-hidden">
                    <div class="relative z-10">
                        <h3 class="text-xl font-black mb-2">Ações Rápidas</h3>
                        <p class="text-indigo-100 text-sm mb-6">Crie novos registros rapidamente.</p>
                        <div class="space-y-3">
                            <a href="{{ route('emprestimos.create') }}" class="flex items-center justify-center w-full py-3 bg-white text-indigo-700 rounded-2xl font-extrabold shadow-lg hover:bg-slate-50 transition">Novo Empréstimo</a>
                            <a href="{{ route('clientes.create') }}" class="flex items-center justify-center w-full py-3 bg-indigo-600 text-white border border-indigo-400 rounded-2xl font-extrabold hover:bg-indigo-500 transition">Novo Cliente</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctxPrincipal = document.getElementById('chartPrincipal').getContext('2d');
            
            // LOGICA DO GRÁFICO: 
            // Espera que você passe do Controller: $graficoLabels, $graficoEmprestado e $graficoPago
            new Chart(ctxPrincipal, {
                type: 'bar',
                data: {
                    labels: @json($graficoLabels ?? ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun']), 
                    datasets: [
                        {
                            label: 'Emprestado',
                            data: @json($graficoEmprestado ?? [12000, 19000, 15000, 22000, 18000, 25000]),
                            backgroundColor: '#6366f1',
                            borderRadius: 8,
                            barPercentage: 0.6,
                        },
                        {
                            label: 'Pago',
                            data: @json($graficoPago ?? [8000, 14000, 12000, 18000, 15000, 21000]),
                            backgroundColor: '#34d399',
                            borderRadius: 8,
                            barPercentage: 0.6,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#e2e8f0' }, border: { display: false } },
                        x: { grid: { display: false } }
                    }
                }
            });

            const ctxRosca = document.getElementById('chartRosca').getContext('2d');
            new Chart(ctxRosca, {
                type: 'doughnut',
                data: {
                    labels: ['Aberto', 'A Vencer', 'Atraso'],
                    datasets: [{
                        data: [@json($aberto), @json($ate30), @json($atraso)],
                        backgroundColor: ['#6366f1', '#fbbf24', '#ef4444'],
                        borderWidth: 0,
                        hoverOffset: 15
                    }]
                },
                options: {
                    cutout: '75%',
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, font: { weight: 'bold' } } } }
                }
            });
        });
    </script>
</x-app-layout>