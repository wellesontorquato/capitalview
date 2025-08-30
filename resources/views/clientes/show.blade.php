<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Cliente</h2>
    </x-slot>

    @php
        // Helpers de formatação
        $digits = fn($v) => preg_replace('/\D+/', '', (string)$v);

        $fmtCpf = function ($v) use ($digits) {
            $d = $digits($v);
            if (strlen($d) !== 11) return $v ?: '—';
            return substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2);
        };

        $fmtCep = function ($v) use ($digits) {
            $d = $digits($v);
            if (strlen($d) !== 8) return $v ?: '—';
            return substr($d,0,5).'-'.substr($d,5,3);
        };

        $fmtPhone = function ($v) use ($digits) {
            $d = $digits($v);
            if (!$d) return '—';
            if (strlen($d) === 11) return sprintf('(%s) %s-%s', substr($d,0,2), substr($d,2,5), substr($d,7,4));
            if (strlen($d) === 10) return sprintf('(%s) %s-%s', substr($d,0,2), substr($d,2,4), substr($d,6,4));
            return $v;
        };

        $waLink = function ($v) use ($digits) {
            $d = $digits($v);
            if (!$d) return null;
            if (strlen($d) === 10 || strlen($d) === 11) $d = '55'.$d; // assume BR
            return 'https://wa.me/'.$d;
        };

        $addrLine = function ($c) {
            $p1 = array_filter([$c->logradouro, $c->numero ? 'nº '.$c->numero : null, $c->complemento]);
            $p2 = array_filter([$c->bairro, $c->cidade && $c->uf ? ($c->cidade.'/'.$c->uf) : ($c->cidade ?: $c->uf)]);
            $l1 = $p1 ? implode(', ', $p1) : null;
            $l2 = $p2 ? implode(' — ', $p2) : null;
            return trim(implode(' • ', array_filter([$l1, $l2])));
        };

        $mapsQ = function($c) use ($addrLine) {
            $full = trim($addrLine($c).' CEP '.$c->cep);
            return 'https://www.google.com/maps/search/?api=1&query='.urlencode($full);
        };

        $tipoShort = fn($t) => match($t){ 'FIXED_ON_PRINCIPAL'=>'A','AMORTIZATION_ON_BALANCE'=>'B', default=>'—' };
        $tipoLabel = fn($t) => match($t){
            'FIXED_ON_PRINCIPAL'      => 'Opção A — Juros fixos sobre o principal',
            'AMORTIZATION_ON_BALANCE' => 'Opção B — Amortização + juros sobre saldo',
            default => '—'
        };
        $pct   = fn($x) => is_numeric($x) ? number_format($x*100, 2, ',', '.') . ' % a.m.' : '—';
        $moeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

        $badge = function($status){
            $map = [
                'quitado' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
                'ativo'   => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
                'atrasado'=> 'bg-red-50 text-red-700 ring-1 ring-red-200',
            ];
            return $map[strtolower((string)$status)] ?? 'bg-slate-50 text-slate-700 ring-1 ring-slate-200';
        };

        $emprestimos = $cliente->emprestimos ?? collect();
    @endphp

    <div class="space-y-6">
        {{-- Cabeçalho + Ações --}}
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <div class="text-2xl font-extrabold text-ink-900 leading-tight">
                    {{ $cliente->nome }}
                    @if($cliente->apelido)
                        <span class="text-slate-500 text-lg font-medium">({{ $cliente->apelido }})</span>
                    @endif
                </div>
                @if($cliente->observacoes)
                    <div class="mt-1 text-sm text-slate-500">Observações registradas</div>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('clientes.edit', $cliente) }}" class="btn btn-ghost">Editar</a>
                <a href="{{ route('emprestimos.create', ['cliente'=>$cliente->id]) }}" class="btn btn-primary">Novo Empréstimo</a>
                <a href="{{ route('clientes.index') }}" class="btn btn-ghost">Voltar</a>
            </div>
        </div>

        {{-- Cards de informações --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Contato / Identificação --}}
            <div class="card">
                <div class="card-p space-y-3">
                    <h3 class="font-semibold text-ink-900">Contato</h3>
                    <div class="text-sm">
                        <div class="text-slate-500">WhatsApp</div>
                        <div>
                            @if($waLink($cliente->whatsapp))
                                <a class="underline text-emerald-700" target="_blank" href="{{ $waLink($cliente->whatsapp) }}">
                                    {{ $fmtPhone($cliente->whatsapp) }}
                                </a>
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-sm">
                        <div class="text-slate-500">E-mail</div>
                        <div>
                            {!! $cliente->email ? '<a class="underline" href="mailto:'.e($cliente->email).'">'.e($cliente->email).'</a>' : '<span class="text-slate-500">—</span>' !!}
                        </div>
                    </div>
                    <div class="text-sm grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-slate-500">CPF</div>
                            <div class="font-medium">{{ $fmtCpf($cliente->cpf) }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500">RG</div>
                            <div class="font-medium">{{ $cliente->rg ?: '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Endereço --}}
            <div class="card">
                <div class="card-p space-y-3">
                    <h3 class="font-semibold text-ink-900">Endereço</h3>
                    <div class="text-sm">
                        <div class="text-slate-500">CEP</div>
                        <div class="font-medium">{{ $fmtCep($cliente->cep) }}</div>
                    </div>
                    <div class="text-sm">
                        <div class="text-slate-500">Logradouro</div>
                        <div class="font-medium">
                            {{ $cliente->logradouro ?: '—' }}
                            @if($cliente->numero) , nº {{ $cliente->numero }} @endif
                            @if($cliente->complemento) — {{ $cliente->complemento }} @endif
                        </div>
                    </div>
                    <div class="text-sm grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-slate-500">Bairro</div>
                            <div class="font-medium">{{ $cliente->bairro ?: '—' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500">Cidade/UF</div>
                            <div class="font-medium">
                                {{ $cliente->cidade && $cliente->uf ? $cliente->cidade.'/'.$cliente->uf : ($cliente->cidade ?: ($cliente->uf ?: '—')) }}
                            </div>
                        </div>
                    </div>
                    @if($addrLine($cliente))
                        <div>
                            <a href="{{ $mapsQ($cliente) }}" target="_blank" class="text-sm underline text-slate-700">
                                Ver no mapa
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Observações --}}
            <div class="card">
                <div class="card-p space-y-3">
                    <h3 class="font-semibold text-ink-900">Observações</h3>
                    <div class="text-sm whitespace-pre-wrap min-h-[64px]">
                        {{ $cliente->observacoes ?: '—' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Empréstimos do cliente --}}
        <div class="card">
            <div class="card-p">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-ink-900">Empréstimos do Cliente</h3>
                    <a href="{{ route('emprestimos.index', ['q' => $cliente->nome]) }}" class="btn btn-ghost">Ver na listagem</a>
                </div>

                {{-- Mobile: cards --}}
                <div class="grid sm:hidden gap-3">
                    @forelse($emprestimos as $e)
                        @php
                            $status = strtolower((string)($e->status ?? ''));
                            // heurística simples p/ badge atrasado
                            $temVencidas = method_exists($e, 'parcelas') ? $e->parcelas->where('status','!=','paga')->where('vencimento','<', now()->toDateString())->count() > 0 : false;
                            if ($temVencidas) $status = 'atrasado';
                        @endphp
                        <div class="border border-slate-200 rounded-2xl p-4 bg-white">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-semibold">Empréstimo #{{ $e->id }}</div>
                                    <div class="text-xs text-slate-500 mt-0.5">
                                        Opção {{ $tipoShort($e->tipo_calculo) }} — {{ $tipoLabel($e->tipo_calculo) }}
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $badge($status) }}">
                                    {{ ucfirst($status ?: 'ativo') }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-sm mt-3">
                                <div>Taxa<br><b>{{ $pct($e->taxa_periodo) }}</b></div>
                                <div>Parcelas<br><b>{{ $e->qtd_parcelas ?? '—' }}</b></div>
                                <div>Principal<br><b>{{ $moeda($e->valor_principal) }}</b></div>
                                <div>Tipo<br><b>Opção {{ $tipoShort($e->tipo_calculo) }}</b></div>
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <a class="btn btn-primary" href="{{ route('emprestimos.show',$e) }}">Abrir</a>
                                <a class="btn btn-ghost" href="{{ route('emprestimos.edit',$e) }}">Editar</a>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">Nenhum empréstimo ainda.</div>
                    @endforelse
                </div>

                {{-- Desktop: tabela --}}
                <div class="table-wrap hidden sm:block">
                    <table class="table min-w-[900px]">
                        <thead>
                            <tr>
                                <th class="th">#</th>
                                <th class="th">Tipo</th>
                                <th class="th">Taxa</th>
                                <th class="th">Parcelas</th>
                                <th class="th">Empréstimo</th>
                                <th class="th">Status</th>
                                <th class="th text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($emprestimos as $e)
                                @php
                                    $status = strtolower((string)($e->status ?? ''));
                                    $temVencidas = method_exists($e, 'parcelas') ? $e->parcelas->where('status','!=','paga')->where('vencimento','<', now()->toDateString())->count() > 0 : false;
                                    if ($temVencidas) $status = 'atrasado';
                                @endphp
                                <tr>
                                    <td class="td">#{{ $e->id }}</td>
                                    <td class="td">
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200"
                                              title="{{ $tipoLabel($e->tipo_calculo) }}">
                                            Opção {{ $tipoShort($e->tipo_calculo) }}
                                        </span>
                                    </td>
                                    <td class="td">{{ $pct($e->taxa_periodo) }}</td>
                                    <td class="td">{{ $e->qtd_parcelas ?? '—' }}</td>
                                    <td class="td">{{ $moeda($e->valor_principal) }}</td>
                                    <td class="td">
                                        <span class="px-2 py-0.5 rounded-full text-xs {{ $badge($status) }}">
                                            {{ ucfirst($status ?: 'ativo') }}
                                        </span>
                                    </td>
                                    <td class="td text-right">
                                        <a class="btn btn-ghost" href="{{ route('emprestimos.show',$e) }}">Abrir</a>
                                        <a class="btn btn-ghost" href="{{ route('emprestimos.edit',$e) }}">Editar</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="td text-slate-500" colspan="7">Nenhum empréstimo ainda.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
