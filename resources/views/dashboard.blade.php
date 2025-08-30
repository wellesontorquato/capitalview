<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Dashboard</h2>
    </x-slot>

    @php
        $moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

        // helpers de export para reaproveitar a query atual
        $hrefExport = function (string $format, string $what) {
            return route('dashboard', array_merge(request()->all(), [
                'export' => $format, // pdf|xlsx|csv
                'what'   => $what,   // kpis|vencimentos
            ]));
        };
    @endphp

    <style>
        /* mini utilitários locais */
        .tnum{font-variant-numeric:tabular-nums}
        .btn-red{background:#ef4444;color:#fff;border-radius:10px;padding:8px 12px}
        .btn-red:hover{background:#dc2626}
        .btn-green{background:#10b981;color:#fff;border-radius:10px;padding:8px 12px}
        .btn-green:hover{background:#059669}
        .kpi-card .stat-title{letter-spacing:.03em}
        .kpi-card .stat-value{font-variant-numeric:tabular-nums}
        .table-modern{width:100%;border-collapse:separate;border-spacing:0}
        .table-modern thead th{
            font-weight:600;font-size:.85rem;letter-spacing:.02em;background:#fff;position:sticky;top:0;z-index:1;
            border-bottom:1px solid #e5e7eb;padding:12px 14px;color:#475569;text-align:left;white-space:nowrap
        }
        .table-modern tbody td{padding:14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        .table-modern tbody tr:hover{background:#fbfcff}
    </style>

    {{-- KPIs + export --}}
    <div class="flex items-start justify-between gap-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 flex-1">
            <div class="stat kpi-card">
                <div class="stat-title">Total emprestado</div>
                <div class="stat-value tnum">{{ $moeda($totalEmprestado) }}</div>
            </div>
            <div class="stat kpi-card">
                <div class="stat-title">Em aberto</div>
                <div class="stat-value tnum">{{ $moeda($aberto) }}</div>
            </div>
            <div class="stat kpi-card">
                <div class="stat-title">A vencer (30d)</div>
                <div class="stat-value tnum">{{ $moeda($ate30) }}</div>
            </div>
            <div class="stat kpi-card">
                <div class="stat-title">Atrasadas</div>
                <div class="stat-value tnum">{{ $moeda($atraso) }}</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Próximos vencimentos --}}
        <div class="card lg:col-span-2">
            <div class="card-p">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <h3 class="font-semibold text-ink-900">
                        Próximos vencimentos — {{ \Illuminate\Support\Carbon::now()->translatedFormat('F/Y') }}
                    </h3>
                    <div class="flex items-center gap-2">
                        <a href="{{ $hrefExport('pdf','vencimentos') }}"  class="btn-red">PDF</a>
                        <a href="{{ $hrefExport('csv','vencimentos') }}" class="btn-green">Excel</a>
                        <a href="{{ route('emprestimos.index') }}" class="btn btn-ghost">Ver todos</a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table-modern min-w-[760px]">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Parcela</th>
                                <th>Vencimento</th>
                                <th class="text-right">Saldo</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($proximas as $p)
                                <tr>
                                    <td class="truncate max-w-[260px]">{{ $p->emprestimo->cliente->nome }}</td>
                                    <td>#{{ $p->numero }} do Empréstimo {{ $p->emprestimo->id }}</td>
                                    <td>{{ $p->vencimento_fmt }}</td>
                                    <td class="text-right tnum">{{ $moeda($p->saldo_atual) }}</td>
                                    <td class="text-right">
                                        <a class="btn btn-primary" href="{{ route('emprestimos.show',$p->emprestimo) }}">Abrir</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-slate-500">Sem vencimentos neste mês.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $proximas->onEachSide(1)->links() }}
                </div>
            </div>
        </div>

        {{-- Ações rápidas --}}
        <div class="card">
            <div class="card-p">
                <h3 class="font-semibold text-ink-900 mb-3">Ações rápidas</h3>
                <div class="flex flex-col gap-2">
                    <a class="btn btn-primary" href="{{ route('emprestimos.create') }}">Novo Empréstimo</a>
                    <a class="btn btn-ghost" href="{{ route('clientes.create') }}">Novo Cliente</a>
                </div>

                <hr class="my-4 border-slate-200">

                <div class="space-y-2">
                    <div class="text-sm text-slate-500">Exportar agora</div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ $hrefExport('pdf','kpis') }}"  class="btn-red">PDF KPIs</a>
                        <a href="{{ $hrefExport('csv','kpis') }}" class="btn-green">Excel KPIs</a>
                        <a href="{{ $hrefExport('pdf','vencimentos') }}"  class="btn-red">PDF Venc.</a>
                        <a href="{{ $hrefExport('csv','vencimentos') }}" class="btn-green">Excel Venc.</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
