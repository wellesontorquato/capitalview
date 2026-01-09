<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                {{ __('Dashboard Financeiro') }}
            </h2>
            <div class="text-sm text-slate-500 font-medium">
                {{ now()->translatedFormat('d \d\e F, Y') }}
            </div>
        </div>
    </x-slot>

    @php
        $moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

        $hrefExport = function (string $format, string $what) {
            return route('dashboard', array_merge(request()->all(), [
                'export' => $format,
                'what'   => $what,
            ]));
        };
    @endphp

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">
        
        {{-- Seção de KPIs --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @php
                $stats = [
                    ['label' => 'Total Emprestado', 'value' => $totalEmprestado, 'color' => 'blue'],
                    ['label' => 'Em Aberto', 'value' => $aberto, 'color' => 'indigo'],
                    ['label' => 'A Vencer (Mês atual)', 'value' => $ate30, 'color' => 'amber'],
                    ['label' => 'Em Atraso', 'value' => $atraso, 'color' => 'red'],
                ];
            @endphp

            @foreach($stats as $stat)
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                    <p class="text-sm font-medium text-slate-500 uppercase tracking-wider">{{ $stat['label'] }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900 tabular-nums">
                        {{ $moeda($stat['value']) }}
                    </p>
                    <div class="mt-4 w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-{{ $stat['color'] }}-500 h-full" style="width: 70%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            {{-- Tabela de Próximos Vencimentos --}}
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Próximos Vencimentos</h3>
                        <p class="text-sm text-slate-500">Competência de {{ \Illuminate\Support\Carbon::now()->translatedFormat('F/Y') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ $hrefExport('pdf','vencimentos') }}" class="inline-flex items-center px-3 py-2 text-sm font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            PDF
                        </a>
                        <a href="{{ $hrefExport('csv','vencimentos') }}" class="inline-flex items-center px-3 py-2 text-sm font-semibold text-emerald-600 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
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
                            @forelse($proximas as $p)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-6 py-4">
                                        <span class="font-medium text-slate-900 block truncate max-w-[200px]">{{ $p->emprestimo->cliente->nome }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        #{{ $p->numero }} <span class="text-slate-400">do ID {{ $p->emprestimo->id }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        {{ $p->vencimento_fmt }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-slate-900 tabular-nums">
                                        {{ $moeda($p->saldo_atual) }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="{{ route('emprestimos.show',$p->emprestimo) }}" class="text-indigo-600 hover:text-indigo-900 font-bold text-sm">Abrir</a>
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

                @if($proximas->hasPages())
                <div class="p-6 border-t border-slate-100">
                    {{ $proximas->links() }}
                </div>
                @endif
            </div>

            {{-- Sidebar de Ações Rápidas --}}
            <div class="space-y-6">
                <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl shadow-slate-200">
                    <h3 class="text-lg font-bold mb-4">Ações Rápidas</h3>
                    <div class="space-y-3">
                        <a href="{{ route('emprestimos.create') }}" class="flex items-center justify-center w-full py-3 px-4 bg-indigo-500 hover:bg-indigo-400 text-white rounded-xl font-bold transition">
                            Novo Empréstimo
                        </a>
                        <a href="{{ route('clientes.create') }}" class="flex items-center justify-center w-full py-3 px-4 bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 rounded-xl font-bold transition">
                            Novo Cliente
                        </a>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-800">
                        <p class="text-xs font-semibold text-slate-400 uppercase mb-4 tracking-widest">Relatórios Globais</p>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="{{ $hrefExport('pdf','kpis') }}" class="text-center py-2 bg-slate-800 rounded-lg text-xs hover:bg-red-900/30 transition">KPIs (PDF)</a>
                            <a href="{{ $hrefExport('csv','kpis') }}" class="text-center py-2 bg-slate-800 rounded-lg text-xs hover:bg-emerald-900/30 transition">KPIs (XLS)</a>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-6">
                    <h4 class="font-bold text-slate-800 mb-2">Dica do Sistema</h4>
                    <p class="text-sm text-slate-500 leading-relaxed">
                        Você tem <strong>{{ $proximas->total() }}</strong> parcelas vencendo este mês. Lembre-se de realizar a conferência antes de exportar o PDF.
                    </p>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>