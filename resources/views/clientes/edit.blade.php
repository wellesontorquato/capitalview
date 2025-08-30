<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Editar Cliente</h2>
    </x-slot>

    <div class="p-6 max-w-4xl space-y-6">
        {{-- Erros globais --}}
        @if ($errors->any())
            <div class="mb-4 p-3 rounded bg-red-100 text-red-800">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('clientes.update', $cliente) }}" class="space-y-8" id="clienteForm">
            @csrf
            @method('PUT')

            {{-- Dados básicos --}}
            <section>
                <h3 class="font-semibold mb-3 text-slate-800">Dados básicos</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm mb-1">Nome <span class="text-red-600">*</span></label>
                        <input name="nome" type="text" required
                               value="{{ old('nome', $cliente->nome) }}"
                               class="border rounded-xl w-full px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        @error('nome')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="block text-sm mb-1">Apelido</label>
                        <input name="apelido" type="text"
                               value="{{ old('apelido', $cliente->apelido) }}"
                               class="border rounded-xl w-full px-3 py-2">
                        @error('apelido')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="block text-sm mb-1">WhatsApp</label>
                        <input name="whatsapp" type="tel" inputmode="numeric" autocomplete="tel"
                               placeholder="(11) 98765-4321"
                               value="{{ old('whatsapp', $cliente->whatsapp) }}"
                               class="border rounded-xl w-full px-3 py-2 js-mask-whatsapp">
                        @error('whatsapp')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="block text-sm mb-1">E-mail (opcional)</label>
                        <input name="email" type="email" autocomplete="email"
                               value="{{ old('email', $cliente->email) }}"
                               class="border rounded-xl w-full px-3 py-2">
                        @error('email')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>

            {{-- Documentos --}}
            <section>
                <h3 class="font-semibold mb-3 text-slate-800">Documentos</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">CPF <span class="text-red-600">*</span></label>
                        <input name="cpf" type="text" inputmode="numeric" required
                               placeholder="000.000.000-00"
                               value="{{ old('cpf', $cliente->cpf) }}"
                               class="border rounded-xl w-full px-3 py-2 js-mask-cpf">
                        @error('cpf')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-sm mb-1">RG (opcional)</label>
                        <input name="rg" type="text"
                               value="{{ old('rg', $cliente->rg) }}"
                               class="border rounded-xl w-full px-3 py-2">
                        @error('rg')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>

            {{-- Endereço --}}
            <section>
                <h3 class="font-semibold mb-3 text-slate-800">Endereço</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm mb-1">CEP</label>
                        <input name="cep" type="text" inputmode="numeric"
                               placeholder="00000-000"
                               value="{{ old('cep', $cliente->cep) }}"
                               class="border rounded-xl w-full px-3 py-2 js-mask-cep js-cep">
                        <small class="text-xs text-slate-500">Digite o CEP e saia do campo para buscar.</small>
                        @error('cep')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Logradouro</label>
                        <input name="logradouro" type="text"
                               value="{{ old('logradouro', $cliente->logradouro) }}"
                               class="border rounded-xl w-full px-3 py-2 js-endereco">
                        @error('logradouro')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mt-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Bairro</label>
                        <input name="bairro" type="text"
                               value="{{ old('bairro', $cliente->bairro) }}"
                               class="border rounded-xl w-full px-3 py-2 js-endereco">
                        @error('bairro')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Cidade</label>
                        <input name="cidade" type="text"
                               value="{{ old('cidade', $cliente->cidade) }}"
                               class="border rounded-xl w-full px-3 py-2 js-endereco">
                        @error('cidade')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-sm mb-1">UF</label>
                        <select name="uf" class="border rounded-xl w-full px-3 py-2 js-endereco">
                            <option value="">—</option>
                            @php $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']; @endphp
                            @foreach($ufs as $uf)
                                <option value="{{ $uf }}" {{ old('uf', $cliente->uf)===$uf?'selected':'' }}>{{ $uf }}</option>
                            @endforeach
                        </select>
                        @error('uf')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Número</label>
                        <input name="numero" type="text"
                               value="{{ old('numero', $cliente->numero) }}"
                               class="border rounded-xl w-full px-3 py-2">
                        @error('numero')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm mb-1">Complemento (opcional)</label>
                        <input name="complemento" type="text"
                               value="{{ old('complemento', $cliente->complemento) }}"
                               class="border rounded-xl w-full px-3 py-2">
                        @error('complemento')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Referência (opcional)</label>
                        <input name="referencia" type="text"
                               value="{{ old('referencia', $cliente->referencia) }}"
                               class="border rounded-xl w-full px-3 py-2">
                        @error('referencia')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>

            {{-- Observações --}}
            <section>
                <h3 class="font-semibold mb-3 text-slate-800">Observações</h3>
                <textarea name="observacoes" rows="4"
                          class="border rounded-xl w-full px-3 py-2">{{ old('observacoes', $cliente->observacoes) }}</textarea>
                @error('observacoes')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
            </section>

            <div class="flex items-center gap-2">
                <a href="{{ route('clientes.show', $cliente) }}" class="px-4 py-2 rounded-xl border">Cancelar</a>
                <button class="btn btn-primary">Atualizar</button>
            </div>
        </form>
    </div>

    {{-- JS: máscaras simples + ViaCEP (igual ao create) --}}
    <script>
        const onlyDigits = (v) => (v || '').replace(/\D+/g, '');

        function maskCPF(input){
            let v = onlyDigits(input.value).slice(0,11);
            v = v.replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            input.value = v;
        }
        function maskCEP(input){
            let v = onlyDigits(input.value).slice(0,8);
            v = v.replace(/(\d{5})(\d)/, '$1-$2');
            input.value = v;
        }
        function maskWhats(input){
            let v = onlyDigits(input.value).slice(0,11);
            if (v.length <= 10) {
                v = v.replace(/(\d{0,2})(\d{0,4})(\d{0,4})/, function(_,a,b,c){
                    return (a?`(${a}`:'') + (a?') ':'') + (b?b:'') + (c?'-'+c:'');
                });
            } else {
                v = v.replace(/(\d{0,2})(\d{0,5})(\d{0,4})/, function(_,a,b,c){
                    return (a?`(${a}`:'') + (a?') ':'') + (b?b:'') + (c?'-'+c:'');
                });
            }
            input.value = v.trim();
        }

        // aplica máscaras e formata o valor inicial
        document.querySelectorAll('.js-mask-cpf').forEach(el=>{
            el.addEventListener('input', ()=>maskCPF(el));
            maskCPF(el);
        });
        document.querySelectorAll('.js-mask-cep').forEach(el=>{
            el.addEventListener('input', ()=>maskCEP(el));
            maskCEP(el);
        });
        document.querySelectorAll('.js-mask-whatsapp').forEach(el=>{
            el.addEventListener('input', ()=>maskWhats(el));
            maskWhats(el);
        });

        // ViaCEP
        const cepInput = document.querySelector('.js-cep');
        if (cepInput){
            cepInput.addEventListener('blur', async ()=>{
                const cep = onlyDigits(cepInput.value);
                if (cep.length !== 8) return;

                try{
                    const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                    if (!res.ok) throw new Error('CEP não encontrado');
                    const data = await res.json();
                    if (data.erro) throw new Error('CEP inválido');

                    const setVal = (name, v) => {
                        const el = document.querySelector(`[name="${name}"]`);
                        if (el && (!el.value || el.value.trim()==='')) el.value = v || '';
                    };
                    setVal('logradouro', data.logradouro);
                    setVal('bairro',     data.bairro);
                    setVal('cidade',     data.localidade);
                    setVal('uf',         data.uf);
                }catch(e){
                    console.warn(e);
                }
            });
        }
    </script>
</x-app-layout>
