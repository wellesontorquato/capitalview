<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Empréstimos</h2></x-slot>

    {{-- Alerts --}}
    @if (session('success'))
        <div class="card mb-3"><div class="card-p text-emerald-700">{{ session('success') }}</div></div>
    @endif
    @if (session('error'))
        <div class="card mb-3"><div class="card-p text-red-700">{{ session('error') }}</div></div>
    @endif

    <style>
        /* Toolbar */
        .toolbar{display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;margin-bottom:18px}
        .toolbar-left{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
        .toolbar-right{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}

        /* Tabela */
        .table-modern{width:100%;border-collapse:separate;border-spacing:0}
        .table-modern thead th{
            font-weight:600;font-size:.85rem;letter-spacing:.02em;background:#fff;position:sticky;top:0;z-index:1;
            border-bottom:1px solid #e5e7eb;padding:12px 14px;color:#475569;text-align:left;white-space:nowrap
        }
        .table-modern tbody td{padding:16px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        .table-modern tbody tr:hover{background:#fbfcff}
        .col-right{text-align:right}
        .tnum{font-variant-numeric:tabular-nums}
        .nowrap{white-space:nowrap}
        .truncate-1{overflow:hidden;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical}

        /* Badges/chips */
        .badge{font-size:11px;padding:3px 8px;border-radius:999px;display:inline-flex;align-items:center;gap:.35rem;border:1px solid transparent}
        .badge-emerald{background:#ecfdf5;color:#047857;border-color:#a7f3d0}
        .badge-amber{background:#fffbeb;color:#b45309;border-color:#fde68a}
        .badge-red{background:#fef2f2;color:#b91c1c;border-color:#fecaca}
        .chip{font-size:11px;line-height:1;padding:6px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe}

        /* Botões export */
        .btn-red{background:#ef4444;color:#fff;border-radius:10px;padding:8px 12px}
        .btn-red:hover{background:#dc2626}
        .btn-green{background:#10b981;color:#fff;border-radius:10px;padding:8px 12px}
        .btn-green:hover{background:#059669}

        /* Menu ações (teleport) */
        .menu-panel{width:210px;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(2,6,23,.15);border:1px solid #e5e7eb;padding:6px}
        .menu-item{display:block;padding:8px 10px;border-radius:8px;font-size:.95rem}
        .menu-item:hover{background:#f8fafc}

        /* Mobile cards */
        .k-card{border:1px solid #e5e7eb;border-radius:16px;padding:14px;background:#fff}
        .k-title{font-weight:600}
        .k-meta{font-size:.8rem;color:#64748b}
        .k-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.6rem}
    </style>

    @php
        $status = request('status');
        $qs = function(array $extra = []) {
            $base = request()->only(['q','status']); $arr = array_merge($base, $extra);
            return array_filter($arr, fn($v) => !is_null($v) && $v !== '');
        };
        $chipTab = fn($active) => $active
            ? 'px-3 py-1.5 rounded-xl bg-slate-900 text-white'
            : 'px-3 py-1.5 rounded-xl bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50';

        $exportHref = function(string $format){
            $query = array_merge(request()->all(), ['export'=>$format]);
            return url()->current() . '?' . http_build_query($query);
        };

        $tipoLabel   = fn($t) => match($t){ 'FIXED_ON_PRINCIPAL'=>'Opção A — Juros fixos sobre o principal', 'AMORTIZATION_ON_BALANCE'=>'Opção B — Amortização + juros sobre saldo', default=>'—' };
        $tipoShort   = fn($t) => match($t){ 'FIXED_ON_PRINCIPAL'=>'A', 'AMORTIZATION_ON_BALANCE'=>'B', default=>'—' };
        $pct         = fn($x) => is_numeric($x) ? number_format($x*100, 2, ',', '.') . ' % a.m.' : '—';
        $moeda       = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
        $statusText  = fn($e) => (($e->vencidas_count ?? 0) > 0) ? 'Atrasado' : ((($e->abertas_count ?? 0) > 0) ? 'Em aberto' : 'Quitado');
        $statusClass = fn($e) => (($e->vencidas_count ?? 0) > 0) ? 'badge badge-red' : ((($e->abertas_count ?? 0) > 0) ? 'badge badge-amber' : 'badge badge-emerald');
    @endphp

    {{-- TOOLBAR --}}
    <div class="toolbar">
        <div class="toolbar-left">
            <form method="GET" class="w-full sm:w-[340px]">
                <input type="text" name="q" placeholder="Buscar cliente ou ID..."
                       value="{{ request('q') }}"
                       class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500">
                @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            </form>

            <a href="{{ route('emprestimos.index', $qs(['status'=>null])) }}" class="{{ $chipTab(!$status) }}">Todos</a>
            <a href="{{ route('emprestimos.index', $qs(['status'=>'aberto'])) }}" class="{{ $chipTab($status==='aberto') }}">Em aberto</a>
            <a href="{{ route('emprestimos.index', $qs(['status'=>'quitado'])) }}" class="{{ $chipTab($status==='quitado') }}">Quitado</a>
        </div>

        <div class="toolbar-right">
            <a href="{{ $exportHref('pdf') }}"  class="btn-red">PDF</a>
            <a href="{{ $exportHref('csv') }}" class="btn-green">Excel</a>
            <a href="{{ route('emprestimos.create') }}" class="btn btn-primary">Novo Empréstimo</a>
        </div>
    </div>

    {{-- MOBILE (cards) --}}
    <div class="grid sm:hidden gap-3">
        @forelse($emprestimos as $e)
            @php $temPagamentos = ($e->pagamentos_count ?? 0) > 0; @endphp
            <div class="k-card" x-data="menu()" x-init="init()">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="k-title">{{ $e->cliente?->nome ?? '—' }}</div>
                        <div class="k-meta">#{{ $e->id }} • Opção {{ $tipoShort($e->tipo_calculo) }} • {{ $pct($e->taxa_periodo) }}</div>
                    </div>
                    <span class="{{ $statusClass($e) }}">{{ $statusText($e) }}</span>
                </div>

                <div class="k-grid mt-3 text-sm">
                    <div>Parcelas<br><b class="tnum">{{ $e->qtd_parcelas ?? '—' }}</b></div>
                    <div>Empréstimo<br><b class="tnum">{{ $moeda($e->valor_principal) }}</b></div>
                    <div>Retorno<br><b class="tnum">{{ $moeda($e->retorno_lucro ?? 0) }}</b></div>
                </div>

                <div class="mt-2 text-right text-sm">
                    Quitado por: <b class="tnum">{{ $e->quitado_por !== null ? $moeda($e->quitado_por) : '—' }}</b>
                </div>

                <div class="mt-3 flex items-center justify-between">
                    <a class="btn btn-primary" href="{{ route('emprestimos.show',$e) }}">Abrir</a>

                    <div x-data="menu()">
                        <button @click="toggle($event)" class="btn btn-ghost px-3" aria-label="Mais ações">⋯</button>

                        <template x-teleport="body">
                            <!-- Overlay: fecha no clique fora e no ESC -->
                            <div
                                x-show="open"
                                x-transition.opacity.duration.100ms
                                @keydown.window.escape.prevent="close()"
                                @click.self="close()"
                                class="fixed inset-0 z-[60] bg-black/0"
                                style="pointer-events:auto"
                            >
                                <!-- Painel (posicionado via JS) -->
                                <div class="menu-panel z-[70]" :style="style" @click.stop>
                                    <a class="menu-item" href="{{ route('emprestimos.edit',$e) }}">Editar</a>

                                    {{-- Exportações INDIVIDUAIS (micro — parcelas) --}}
                                    <a class="menu-item" href="{{ route('emprestimos.show',$e) }}?export=parcelas-pdf">PDF</a>
                                    <a class="menu-item" href="{{ route('emprestimos.show',$e) }}?export=parcelas-csv">Excel</a>

                                    <form method="POST"
                                        action="{{ route('emprestimos.destroy', $e) }}"
                                        class="menu-item js-del-form"
                                        data-emprestimo-id="{{ $e->id }}"
                                        data-has-payments="{{ $temPagamentos ? '1' : '0' }}">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="force" value="">
                                        <button type="submit" class="text-red-600 w-full text-left">Excluir</button>
                                    </form>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-sm text-slate-500">Nenhum empréstimo encontrado.</div>
        @endforelse
    </div>

    {{-- DESKTOP (tabela) --}}
    <div class="hidden sm:block">
        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
            <div class="overflow-x-auto md:overflow-visible">
                <table class="table-modern w-full">
                    <thead>
                    <tr>
                        <th class="col-right">#</th>
                        <th class="min-w-[260px]">Cliente</th>
                        <th>Tipo</th>
                        <th class="col-right">Taxa</th>
                        <th class="col-right">Parcelas</th>
                        <th class="col-right">Empréstimo</th>
                        <th>Status</th>
                        <th class="col-right">Retorno</th>
                        <th class="col-right">Quitado por</th>
                        <th class="text-right">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($emprestimos as $e)
                        @php $temPagamentos = ($e->pagamentos_count ?? 0) > 0; @endphp
                        <tr x-data="menu()" x-init="init()">
                            <td class="tnum col-right">{{ $e->id }}</td>
                            <td class="truncate-1">{{ $e->cliente?->nome ?? '—' }}</td>
                            <td class="nowrap">
                                <span class="chip" title="{{ $tipoLabel($e->tipo_calculo) }}">Opção {{ $tipoShort($e->tipo_calculo) }}</span>
                            </td>
                            <td class="tnum col-right nowrap">{{ $pct($e->taxa_periodo) }}</td>
                            <td class="tnum col-right nowrap">{{ $e->qtd_parcelas ?? '—' }}</td>
                            <td class="tnum col-right nowrap">{{ $moeda($e->valor_principal) }}</td>
                            <td class="nowrap">
                                <span class="{{ $statusClass($e) }}">{{ $statusText($e) }}</span>
                                @if(($e->vencidas_count ?? 0) > 0)
                                    <span class="ml-2 text-xs text-red-600">({{ $e->vencidas_count }} venc.)</span>
                                @endif
                            </td>
                            <td class="tnum col-right nowrap">{{ $moeda($e->retorno_lucro ?? 0) }}</td>
                            <td class="tnum col-right nowrap">
                                {{ $e->quitado_por !== null ? $moeda($e->quitado_por) : '—' }}
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a class="btn btn-ghost mr-2" href="{{ route('emprestimos.show',$e) }}">Abrir</a>
                                <button @click="toggle($event)" class="btn btn-ghost px-3" aria-label="Mais ações">⋯</button>

                                <template x-teleport="body">
                                    <div x-show="open" x-transition.opacity
                                         @keydown.window.escape="close()"
                                         @click.self="close()"
                                         class="fixed inset-0 z-40 bg-black/0">
                                        <div class="menu-panel z-50" :style="style" @click.stop>
                                            <a class="menu-item" href="{{ route('emprestimos.edit',$e) }}">Editar</a>
                                            <a class="menu-item" href="{{ route('emprestimos.show',$e) }}?export=parcelas-pdf">PDF</a>
                                            <a class="menu-item" href="{{ route('emprestimos.show',$e) }}?export=parcelas-xlsx">Excel</a>
                                            <form method="POST" action="{{ route('emprestimos.destroy', $e) }}"
                                                  class="menu-item js-del-form"
                                                  data-emprestimo-id="{{ $e->id }}"
                                                  data-has-payments="{{ $temPagamentos ? '1' : '0' }}">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="force" value="">
                                                <button type="submit" class="text-red-600 w-full text-left">Excluir</button>
                                            </form>
                                        </div>
                                    </div>
                                </template>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-slate-500" colspan="10">Nenhum empréstimo encontrado.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4">{{ $emprestimos->links() }}</div>

    {{-- Alpine helpers + confirmação de exclusão (delegação) --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        /* Comportamento do menu "⋯" */
        function menu(){
            return {
                open:false, rect:{top:0,left:0,bottom:0,right:0}, w:210,
                get style(){
                    const m=8, vw=innerWidth, vh=innerHeight, h=180;
                    let x=this.rect.right-this.w; if(x<8)x=8; if(x+this.w>vw-8)x=vw-this.w-8;
                    const below=this.rect.bottom+m, spaceBelow=vh-this.rect.bottom;
                    let y=(spaceBelow>h?below:this.rect.top-m-h); if(y<8)y=8;
                    return `position:fixed;left:${x}px;top:${y}px;width:${this.w}px`;
                },
                toggle(ev){
                    this.rect = ev.currentTarget.getBoundingClientRect();
                    // fecha outros menus
                    window.dispatchEvent(new CustomEvent('menu:open'));
                    this.open = !this.open;
                },
                close(){ this.open=false; },
                init(){
                    // fecha este quando outro abrir
                    window.addEventListener('menu:open', () => { this.open=false; });
                    // fecha ao redimensionar/rolar
                    window.addEventListener('resize', () => this.close());
                    window.addEventListener('scroll', () => this.close(), true);
                }
            }
        }

        // Delegação: funciona mesmo com x-teleport
        document.addEventListener('submit', function(ev){
            const form = ev.target.closest('.js-del-form');
            if(!form) return;

            ev.preventDefault();

            const hasPayments = form.dataset.hasPayments === '1';
            const loanId = form.dataset.emprestimoId || '';

            const ensureHiddenForceInput = (f)=>{
                let input = f.querySelector('input[name="force"]');
                if(!input){
                    input = document.createElement('input');
                    input.type='hidden'; input.name='force';
                    f.appendChild(input);
                }
                return input;
            };

            const title = hasPayments ? 'Excluir com pagamentos?' : 'Excluir empréstimo?';
            const html  = hasPayments
                ? `Este empréstimo (#${loanId}) possui <b>pagamentos</b>.<br>Deseja <b>forçar</b> e apagar tudo (pagamentos, ajustes e parcelas)?`
                : `Tem certeza que deseja excluir o empréstimo #${loanId}?`;

            if (typeof Swal === 'undefined') {
                const msg = hasPayments
                    ? `Há parcelas com pagamento.\nForçar exclusão do empréstimo #${loanId}?`
                    : `Excluir o empréstimo #${loanId}?`;
                if (confirm(msg)) {
                    ensureHiddenForceInput(form).value = hasPayments ? '1' : '';
                    form.submit();
                }
                return;
            }

            Swal.fire({
                title, html, icon:'warning',
                showCancelButton:true,
                confirmButtonText: hasPayments ? 'Forçar exclusão' : 'Excluir',
                cancelButtonText:'Cancelar',
                reverseButtons:true,
            }).then(res=>{
                if(res.isConfirmed){
                    ensureHiddenForceInput(form).value = hasPayments ? '1' : '';
                    form.submit();
                }
            });
        }, true);
    </script>
</x-app-layout>
