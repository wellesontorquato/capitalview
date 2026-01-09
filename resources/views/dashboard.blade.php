<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-900 leading-tight">
                    {{ __('Dashboard Financeiro') }}
                </h2>
                <p class="text-sm text-slate-500 font-medium">Gestão de carteira e recebimentos</p>
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
            return route('dashboard', array_merge(request()->all(), ['export' => $format, 'what' => $what]));
        };
    @endphp

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">

        {{-- Filtros --}}
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <form method="GET" action="{{ request()->url() }}" class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1 block">Início</label>
                    <input type="date" name="de" value="{{ request('de') }}" class="w-full rounded-xl border-slate-200 text-sm focus:ring-indigo-500">
                </div>
                <div>
                    <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1 block">Fim</label>
                    <input type="date" name="ate" value="{{ request('ate') }}" class="w-full rounded-xl border-slate-200 text-sm focus:ring-indigo-500">
                </div>
                <div>
                    <label class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1 block">Busca</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Cliente..." class="w-full rounded-xl border-slate-200 text-sm focus:ring-indigo-500">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 py-2.5 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-slate-800 transition">Filtrar</button>
                    <a href="{{ request()->url() }}" class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl font-bold text-sm">Reset</a>
                </div>
            </form>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @php
                $cards = [
                    ['label' => 'Total Emprestado', 'value' => $totalEmprestado, 'color' => 'blue'],
                    ['label' => 'Total em Aberto', 'value' => $aberto, 'color' => 'indigo'],
                    ['label' => 'A Vencer (30d)', 'value' => $ate30, 'color' => 'amber'],
                    ['label' => 'Total em Atraso', 'value' => $atraso, 'color' => 'red'],
                ];
            @endphp
            @foreach($cards as $c)
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $c['label'] }}</p>
                    <h4 class="text-2xl font-black text-slate-900 mt-1 tabular-nums">{{ $moeda($c['value']) }}</h4>
                    <div class="mt-4 w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-{{ $c['color'] }}-500 h-full w-2/3"></div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Gráficos --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Emprestado vs Pago</h3>
                        <p class="text-sm text-slate-500">Comparativo de fluxo</p>
                    </div>
                </div>
                <div class="h-[300px]">
                    <canvas id="chartPrincipal"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-6">Status Geral</h3>
                <div class="h-[300px]">
                    <canvas id="chartRosca"></canvas>
                </div>
            </div>
        </div>

        {{-- Tabela --}}
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800">Próximos Vencimentos</h3>
                <div class="flex gap-2 text-xs font-bold uppercase tracking-widest text-slate-400">
                    Mês de {{ now()->translatedFormat('F') }}
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-slate-400 font-bold uppercase text-[10px]">
                        <tr>
                            <th class="px-6 py-4">Cliente</th>
                            <th class="px-6 py-4">Parcela</th>
                            <th class="px-6 py-4">Vencimento</th>
                            <th class="px-6 py-4 text-right">Saldo</th>
                            <th class="px-6 py-4 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @foreach ($proximas as $p)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-6 py-4 font-bold text-slate-900">{{ $p->emprestimo->cliente->nome }}</td>
                                <td class="px-6 py-4 text-slate-500">#{{ $p->numero }}</td>
                                <td class="px-6 py-4">{{ $p->vencimento_fmt }}</td>
                                <td class="px-6 py-4 text-right tabular-nums font-bold">{{ $moeda($p->saldo_atual) }}</td>
                                <td class="px-6 py-4 text-center">
                                    <a href="{{ route('emprestimos.show', $p->emprestimo) }}" class="text-indigo-600 font-bold hover:underline">Abrir</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-50">
                {{ $proximas->links() }}
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gráfico Principal (Emprestado vs Pago)
            new Chart(document.getElementById('chartPrincipal'), {
                type: 'bar',
                data: {
                    labels: {!! json_encode($graficoLabels ?? ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun']) !!},
                    datasets: [
                        {
                            label: 'Emprestado',
                            data: {!! json_encode($graficoEmprestado ?? [0,0,0,0,0,0]) !!},
                            backgroundColor: '#6366f1',
                            borderRadius: 6
                        },
                        {
                            label: 'Pago',
                            data: {!! json_encode($graficoPago ?? [0,0,0,0,0,0]) !!},
                            backgroundColor: '#10b981',
                            borderRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });

            // Gráfico Rosca
            new Chart(document.getElementById('chartRosca'), {
                type: 'doughnut',
                data: {
                    labels: ['Aberto', 'A Vencer', 'Atraso'],
                    datasets: [{
                        data: [{{ $aberto }}, {{ $ate30 }}, {{ $atraso }}],
                        backgroundColor: ['#6366f1', '#f59e0b', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: { cutout: '70%', plugins: { legend: { position: 'bottom' } } }
            });
        });
    </script>
</x-app-layout>