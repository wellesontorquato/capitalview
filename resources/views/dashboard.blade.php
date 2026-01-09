<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                    {{ __('Dashboard Financeiro') }}
                </h2>
                <p class="text-sm text-slate-500 font-medium">Bem-vindo, aqui está o resumo operacional de hoje.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden md:inline-flex items-center px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-bold uppercase tracking-wider">
                    {{ now()->translatedFormat('l, d F') }}
                </span>
            </div>
        </div>
    </x-slot>

    @php
        $moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
        $hrefExport = function (string $format, string $what) {
            return route('dashboard', array_merge(request()->all(), ['export' => $format, 'what' => $what]));
        };
    @endphp

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
        
        {{-- Barra de Filtros Rápidos --}}
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[240px]">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </span>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar cliente ou contrato..." class="w-full pl-10 pr-4 py-2 border-slate-200 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <select name="periodo" class="text-sm border-slate-200 rounded-xl focus:ring-indigo-500 focus:border-indigo-500 py-2">
                        <option value="30">Últimos 30 dias</option>
                        <option value="60">Últimos 60 dias</option>
                        <option value="90">Últimos 90 dias</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm font-bold hover:bg-slate-700 transition">Filtrar</button>
                    <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-200">Limpar</a>
                </div>
            </form>
        </div>

        {{-- Gráficos e KPIs Principais --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            {{-- Coluna de KPIs (Esquerda) --}}
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 p-6 rounded-2xl shadow-lg shadow-indigo-200 text-white">
                    <p class="text-indigo-100 text-xs font-bold uppercase tracking-widest">Total Emprestado</p>
                    <p class="text-2xl font-black mt-1 tabular-nums">{{ $moeda($totalEmprestado) }}</p>
                    <div class="mt-4 flex items-center text-xs text-indigo-200">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5V3a1 1 0 011 1v5h-2a1 1 0 110-2h-4zm-8 4a1 1 0 100 2h4a1 1 0 100-2H4zm10 2a1 1 0 100 2h4a1 1 0 100-2h-4z" clip-rule="evenodd"></path></svg>
                        Carteira Ativa
                    </div>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <p class="text-slate-500 text-xs font-bold uppercase tracking-widest">Em Atraso</p>
                    <p class="text-2xl font-black mt-1 text-red-600 tabular-nums">{{ $moeda($atraso) }}</p>
                    <div class="mt-2 w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-red-500 h-full" style="width: 15%"></div>
                    </div>
                </div>
            </div>

            {{-- Gráfico Central --}}
            <div class="lg:col-span-3 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm min-h-[300px]">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="font-bold text-slate-800 italic">Visão Geral de Recebimentos</h3>
                    <div class="flex gap-2">
                        <span class="flex items-center text-xs text-slate-500"><span class="w-3 h-3 bg-indigo-500 rounded-full mr-1"></span> Previsto</span>
                        <span class="flex items-center text-xs text-slate-500"><span class="w-3 h-3 bg-emerald-400 rounded-full mr-1"></span> Realizado</span>
                    </div>
                </div>
                <canvas id="mainChart" class="max-h-[250px] w-full"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Tabela de Próximos Vencimentos --}}
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4 bg-slate-50/50">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Vencimentos do Mês</h3>
                        <p class="text-sm text-slate-500">Exibindo registros de {{ \Illuminate\Support\Carbon::now()->translatedFormat('F/Y') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                         <div class="dropdown relative group">
                            <button type="button" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-xl text-sm font-bold flex items-center hover:bg-slate-50">
                                Exportar <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-xl hidden group-hover:block z-50">
                                <a href="{{ $hrefExport('pdf','vencimentos') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-t-xl leading-relaxed">Baixar em PDF</a>
                                <a href="{{ $hrefExport('csv','vencimentos') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-b-xl leading-relaxed">Planilha Excel</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Cliente</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Vencimento</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Saldo Devedor</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase tracking-widest text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($proximas as $p)
                                <tr class="hover:bg-indigo-50/30 transition cursor-pointer" onclick="window.location='{{ route('emprestimos.show',$p->emprestimo) }}'">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-xs mr-3">
                                                {{ substr($p->emprestimo->cliente->nome, 0, 2) }}
                                            </div>
                                            <span class="font-semibold text-slate-700 truncate max-w-[150px]">{{ $p->emprestimo->cliente->nome }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 font-medium">
                                        {{ $p->vencimento_fmt }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold text-slate-900 tabular-nums">
                                        {{ $moeda($p->saldo_atual) }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                         <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-tighter {{ $p->vencimento < now() ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                            {{ $p->vencimento < now() ? 'Atrasado' : 'No Prazo' }}
                                         </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-12 text-center text-slate-400 font-medium">Nenhum registro encontrado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($proximas->hasPages())
                <div class="p-6 bg-slate-50 border-t border-slate-100">
                    {{ $proximas->links() }}
                </div>
                @endif
            </div>

            {{-- Sidebar Lateral --}}
            <div class="space-y-6">
                <div class="bg-slate-900 rounded-3xl p-8 text-white shadow-2xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mr-16 -mt-16 w-40 h-40 bg-indigo-500/20 rounded-full blur-3xl"></div>
                    <h3 class="text-xl font-black mb-6 relative z-10">Gestão Rápida</h3>
                    <div class="space-y-4 relative z-10">
                        <a href="{{ route('emprestimos.create') }}" class="group flex items-center justify-between w-full p-4 bg-indigo-600 hover:bg-indigo-500 rounded-2xl transition-all shadow-lg shadow-indigo-900/40">
                            <span class="font-bold">Novo Empréstimo</span>
                            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        </a>
                        <a href="{{ route('clientes.create') }}" class="flex items-center justify-between w-full p-4 bg-slate-800 hover:bg-slate-700 rounded-2xl border border-slate-700 transition">
                            <span class="font-bold text-slate-300">Cadastrar Cliente</span>
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        </a>
                    </div>
                </div>

                <div class="bg-indigo-50 rounded-2xl p-6 border border-indigo-100">
                    <h4 class="font-bold text-indigo-900 flex items-center gap-2 mb-2 text-sm uppercase">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm1 11H9v-2h2v2zm0-4H9V7h2v2z"></path></svg>
                        Resumo de Metas
                    </h4>
                    <p class="text-xs text-indigo-700 font-medium mb-3 leading-relaxed">Você atingiu 85% da meta de recebimento projetada para este mês.</p>
                    <div class="w-full bg-indigo-200 h-2 rounded-full overflow-hidden">
                        <div class="bg-indigo-600 h-full rounded-full" style="width: 85%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Script para Gráficos --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('mainChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4'],
                datasets: [{
                    label: 'Recebimentos',
                    data: [12000, 19000, 15000, 25000],
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#6366f1',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { display: false, beginAtZero: true },
                    x: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: '#94a3b8' } }
                }
            }
        });
    </script>
</x-app-layout>