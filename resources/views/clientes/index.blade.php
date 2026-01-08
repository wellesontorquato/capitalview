<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">Clientes</h2></x-slot>

    @php
        // Helpers de exibição
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
            // tenta formatar como (11) 98765-4321 ou (11) 8765-4321
            if (strlen($d) === 11) {
                return sprintf('(%s) %s-%s', substr($d,0,2), substr($d,2,5), substr($d,7,4));
            }
            if (strlen($d) === 10) {
                return sprintf('(%s) %s-%s', substr($d,0,2), substr($d,2,4), substr($d,6,4));
            }
            return $v; // fallback
        };

        $waLink = function ($v) use ($digits) {
            $d = $digits($v);
            if (!$d) return null;
            // se veio sem DDI, assume +55
            if (strlen($d) === 10 || strlen($d) === 11) $d = '55'.$d;
            return 'https://wa.me/'.$d;
        };

        $addrLine = function ($c) {
            $p1 = array_filter([
                $c->logradouro,
                $c->numero ? 'nº '.$c->numero : null,
            ]);
            $p2 = array_filter([
                $c->bairro,
                $c->cidade && $c->uf ? ($c->cidade.'/'.$c->uf) : ($c->cidade ?: $c->uf),
            ]);
            $l1 = $p1 ? implode(', ', $p1) : null;
            $l2 = $p2 ? implode(' — ', $p2) : null;
            return trim(implode(' • ', array_filter([$l1, $l2]))) ?: '—';
        };
    @endphp

    <div class="flex justify-between items-center mb-4 gap-3">
        <form method="GET" class="flex-1 max-w-md">
            <input type="text" name="q" placeholder="Buscar por nome, apelido, CPF, WhatsApp..."
                   value="{{ request('q') }}"
                   class="w-full rounded-xl border-slate-300 focus:ring-brand-500 focus:border-brand-500">
        </form>
        <a href="{{ route('clientes.create') }}" class="btn btn-primary">Novo Cliente</a>
    </div>

    {{-- Mobile: cards --}}
    <div class="grid sm:hidden gap-3">
        @forelse($clientes as $c)
            <div class="card card-p">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold">
                            {{ $c->nome }}
                            @if($c->apelido)
                                <span class="text-slate-500 font-normal">({{ $c->apelido }})</span>
                            @endif
                        </div>
                        <div class="text-xs text-slate-500 mt-1">
                            CPF: <b>{{ $fmtCpf($c->cpf) }}</b>
                            @if($c->rg) • RG: <b>{{ $c->rg }}</b>@endif
                        </div>
                    </div>
                    <div class="text-xs text-slate-500" title="{{ $c->observacoes }}">
                        {{-- Mostrar um ••• se tiver observação --}}
                        @if($c->observacoes) <span>📝</span> @endif
                    </div>
                </div>

                <div class="mt-3 text-sm space-y-1">
                    <div>
                        WhatsApp:
                        @if($waLink($c->whatsapp))
                            <a class="text-emerald-700 underline" target="_blank" href="{{ $waLink($c->whatsapp) }}">
                                {{ $fmtPhone($c->whatsapp) }}
                            </a>
                        @else
                            <span class="text-slate-500">—</span>
                        @endif
                    </div>
                    <div>E-mail: {!! $c->email ? '<a class="underline" href="mailto:'.e($c->email).'">'.e($c->email).'</a>' : '<span class="text-slate-500">—</span>' !!}</div>
                    <div>Endereço: <span title="{{ $addrLine($c) }}">{{ $addrLine($c) }}</span></div>
                    <div>CEP: {{ $fmtCep($c->cep) }}</div>
                </div>

                <div class="mt-3 flex gap-2">
                    <a class="btn btn-ghost" href="{{ route('clientes.show',$c) }}">Abrir</a>
                    <a class="btn btn-ghost" href="{{ route('clientes.edit',$c) }}">Editar</a>
                </div>
            </div>
        @empty
            <div class="text-sm text-slate-500">Nenhum cliente encontrado.</div>
        @endforelse
    </div>

    {{-- Desktop: tabela --}}
    <div class="table-wrap hidden sm:block">
        <table class="table min-w-[1000px]">
            <thead>
                <tr>
                    <th class="th">Nome</th>
                    <th class="th">Apelido</th>
                    <th class="th">CPF</th>
                    <th class="th">WhatsApp</th>
                    <th class="th">E-mail</th>
                    <th class="th">Cidade/UF</th>
                    <th class="th">CEP</th>
                    <th class="th text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clientes as $c)
                    <tr title="{{ $addrLine($c) }}{{ $c->observacoes ? ' • Obs: '.strip_tags($c->observacoes) : '' }}">
                        <td class="td">
                            <div class="font-medium">{{ $c->nome }}</div>
                        </td>
                        <td class="td">{{ $c->apelido ?: '—' }}</td>
                        <td class="td">{{ $fmtCpf($c->cpf) }}</td>
                        <td class="td">
                            @if($waLink($c->whatsapp))
                                <a class="underline" target="_blank" href="{{ $waLink($c->whatsapp) }}">{{ $fmtPhone($c->whatsapp) }}</a>
                            @else
                                <span class="text-slate-500">—</span>
                            @endif
                        </td>
                        <td class="td">
                            {!! $c->email ? '<a class="underline" href="mailto:'.e($c->email).'">'.e($c->email).'</a>' : '<span class="text-slate-500">—</span>' !!}
                        </td>
                        <td class="td">
                            {{ $c->cidade && $c->uf ? $c->cidade.'/'.$c->uf : ($c->cidade ?: ($c->uf ?: '—')) }}
                        </td>
                        <td class="td">{{ $fmtCep($c->cep) }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <a class="btn btn-ghost" href="{{ route('clientes.show',$c) }}">Abrir</a>
                            <a class="btn btn-ghost" href="{{ route('clientes.edit',$c) }}">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr><td class="td text-slate-500" colspan="8">Nenhum cliente encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $clientes->links() }}</div>
</x-app-layout>
