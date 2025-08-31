<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Novo Cliente</h2>
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

        <form id="form-cliente" method="POST" action="{{ route('clientes.store') }}">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">

            {{-- Dados básicos --}}
            <section>
                <h3 class="font-semibold mb-3 text-slate-800">Dados básicos</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm mb-1">Nome <span class="text-red-600">*</span></label>
                        <input name="nome" type="text" required
                               value="{{ old('nome') }}"
                               class="border rounded-xl w-full px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        @error('nome')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="block text-sm mb-1">Apelido</label>
                        <input name="apelido" type="text"
                               value="{{ old('apelido') }}"
                               class="border rounded-xl w-full px-3 py-2">
                        @error('apelido')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="block text-sm mb-1">WhatsApp</label>
                        <input name="whatsapp" type="tel" inputmode="numeric" autocomplete="tel"
                               placeholder="(11) 98765-4321"
                               value="{{ old('whatsapp') }}"
                               class="border rounded-xl w-full px-3 py-2 js-mask-whatsapp">
                        @error('whatsapp')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="block text-sm mb-1">E-mail (opcional)</label>
                        <input name="email" type="email" autocomplete="email" autocapitalize="off"
                               value="{{ old('email') }}"
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
                               value="{{ old('cpf') }}"
                               class="border rounded-xl w-full px-3 py-2 js-mask-cpf">
                        @error('cpf')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-sm mb-1">RG (opcional)</label>
                        <input name="rg" type="text"
                               value="{{ old('rg') }}"
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
                               value="{{ old('cep') }}"
                               class="border rounded-xl w-full px-3 py-2 js-mask-cep js-cep">
                        <small class="text-xs text-slate-500">Digite o CEP e saia do campo para buscar.</small>
                        @error('cep')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Logradouro</label>
                        <input name="logradouro" type="text"
                               value="{{ old('logradouro') }}"
                               class="border rounded-xl w-full px-3 py-2 js-endereco">
                        @error('logradouro')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mt-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Bairro</label>
                        <input name="bairro" type="text"
                               value="{{ old('bairro') }}"
                               class="border rounded-xl w-full px-3 py-2 js-endereco">
                        @error('bairro')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Cidade</label>
                        <input name="cidade" type="text"
                               value="{{ old('cidade') }}"
                               class="border rounded-xl w-full px-3 py-2 js-endereco">
                        @error('cidade')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-sm mb-1">UF</label>
                        <select name="uf" class="border rounded-xl w-full px-3 py-2 js-endereco">
                            <option value="">—</option>
                            @php $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']; @endphp
                            @foreach($ufs as $uf)
                                <option value="{{ $uf }}" {{ old('uf')===$uf?'selected':'' }}>{{ $uf }}</option>
                            @endforeach
                        </select>
                        @error('uf')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Número</label>
                        <input name="numero" type="text"
                               value="{{ old('numero') }}"
                               class="border rounded-xl w-full px-3 py-2">
                        @error('numero')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm mb-1">Complemento (opcional)</label>
                        <input name="complemento" type="text"
                               value="{{ old('complemento') }}"
                               class="border rounded-xl w-full px-3 py-2">
                        @error('complemento')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Referência (opcional)</label>
                        <input name="referencia" type="text"
                               value="{{ old('referencia') }}"
                               class="border rounded-xl w-full px-3 py-2">
                        @error('referencia')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>

            {{-- Observações --}}
            <section>
                <h3 class="font-semibold mb-3 text-slate-800">Observações</h3>
                <textarea name="observacoes" rows="4"
                          class="border rounded-xl w-full px-3 py-2">{{ old('observacoes') }}</textarea>
                @error('observacoes')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
            </section>

            <div class="flex items-center gap-2">
                <a href="{{ route('clientes.index') }}" class="px-4 py-2 rounded-xl border">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>

    {{-- JS: máscaras + ViaCEP + normalização + validação + trava de submit/Enter --}}
    <script>
    (function () {
    const $ = (sel, ctx=document) => ctx.querySelector(sel);
    const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));
    const onlyDigits = v => (v || '').replace(/\D+/g, '');

    const form = $('#form-cliente');
    if (!form) return; // página ainda não montou

    // -------------------- helpers UI --------------------
    const notify = (opts) => {
        if (window.Swal) {
        Swal.fire(Object.assign({icon:'info', confirmButtonText:'OK'}, opts));
        } else {
        alert(opts.title ? `${opts.title}\n\n${opts.text||''}` : (opts.text||''));
        }
    };

    const setSubmitting = (yes) => {
        form.dataset.submitted = yes ? '1' : '0';
        const btn = form.querySelector('button[type=submit]');
        if (btn) {
        btn.disabled = !!yes;
        btn.dataset._label ??= btn.textContent;
        btn.textContent = yes ? 'Salvando…' : btn.dataset._label;
        }
        // bloqueia todos inputs para evitar alterações durante submit
        $$('input, select, textarea', form).forEach(el => el.readOnly = !!yes);
        form.classList.toggle('opacity-50', !!yes);
    };

    // -------------------- validações simples --------------------
    function cpfIsValid(raw) {
        const s = onlyDigits(raw);
        if (s.length !== 11 || /^(\d)\1{10}$/.test(s)) return false;
        const dv = (base) => {
        let sum = 0, w = base.length + 1;
        for (let c of base) sum += parseInt(c,10) * w--;
        let r = sum % 11; r = (r < 2) ? 0 : 11 - r;
        return r;
        };
        const d1 = dv(s.slice(0,9));
        const d2 = dv(s.slice(0,9) + d1);
        return s.endsWith(`${d1}${d2}`);
    }

    const whatsappIsValid = (raw) => {
        const d = onlyDigits(raw);
        return d.length === 10 || d.length === 11;
    };

    const cepIsValid = (raw) => onlyDigits(raw).length === 8;

    // -------------------- Enter não submete (exceto textarea) --------------------
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') e.preventDefault();
    });

    // -------------------- máscaras visuais (sem “puxar” cursor) --------------------
    function maskCPF(input){
        const start = input.selectionStart;
        let v = onlyDigits(input.value).slice(0,11)
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        input.value = v; try { input.setSelectionRange(start, start); } catch {}
    }
    function maskCEP(input){
        const start = input.selectionStart;
        let v = onlyDigits(input.value).slice(0,8).replace(/(\d{5})(\d)/, '$1-$2');
        input.value = v; try { input.setSelectionRange(start, start); } catch {}
    }
    function maskWhats(input){
        const start = input.selectionStart;
        let d = onlyDigits(input.value).slice(0,11);
        let v;
        if (d.length <= 10)
        v = d.replace(/(\d{0,2})(\d{0,4})(\d{0,4})/, (_,a,b,c)=>(a?`(${a}`:'')+(a?') ':'')+(b||'')+(c?'-'+c:''));
        else
        v = d.replace(/(\d{0,2})(\d{0,5})(\d{0,4})/, (_,a,b,c)=>(a?`(${a}`:'')+(a?') ':'')+(b||'')+(c?'-'+c:''));
        input.value = v.trim(); try { input.setSelectionRange(start, start); } catch {}
    }

    $$('.js-mask-cpf').forEach(el => { el.addEventListener('input', ()=>maskCPF(el)); maskCPF(el); });
    $$('.js-mask-cep').forEach(el => { el.addEventListener('input', ()=>maskCEP(el)); maskCEP(el); });
    $$('.js-mask-whatsapp').forEach(el => { el.addEventListener('input', ()=>maskWhats(el)); maskWhats(el); });

    // paste: só dígitos
    $$('.js-mask-cpf, .js-mask-cep, .js-mask-whatsapp').forEach(el => {
        el.addEventListener('paste', (e)=>{
        e.preventDefault();
        const t = (e.clipboardData || window.clipboardData).getData('text');
        el.value = onlyDigits(t);
        el.dispatchEvent(new Event('input'));
        });
    });

    // -------------------- ViaCEP (com timeout e sem sobrescrever campos já preenchidos) --------------------
    const cepInput = $('.js-cep');
    if (cepInput) {
        let inflight = null;
        cepInput.addEventListener('blur', async () => {
        const cep = onlyDigits(cepInput.value);
        if (cep.length !== 8) return;

        try {
            if (inflight) inflight.abort();
            const ctl = new AbortController(); inflight = ctl;
            const timer = setTimeout(()=>ctl.abort(), 4000);

            const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`, {signal: ctl.signal});
            clearTimeout(timer); inflight = null;

            if (!res.ok) throw new Error('CEP não encontrado');
            const data = await res.json();
            if (data.erro) throw new Error('CEP inválido');

            const setIfEmpty = (name, v) => {
            const el = $(`[name="${name}"]`);
            if (el && (!el.value || el.value.trim() === '')) el.value = v || '';
            };
            setIfEmpty('logradouro', data.logradouro);
            setIfEmpty('bairro',     data.bairro);
            setIfEmpty('cidade',     data.localidade);
            const ufSel = $('[name="uf"]');
            if (ufSel && (!ufSel.value || ufSel.value === '')) ufSel.value = data.uf || '';
        } catch (e) {
            console.warn(e);
        }
        });
    }

    // -------------------- normalização + validação + trava dupla --------------------
    form.addEventListener('submit', (e) => {
        if (form.dataset.submitted === '1') { e.preventDefault(); return; }

        const cpf      = $('[name="cpf"]');
        const whatsapp = $('[name="whatsapp"]');
        const cep      = $('[name="cep"]');
        const email    = $('[name="email"]');

        if (cpf)      cpf.value      = onlyDigits(cpf.value).slice(0,11);
        if (whatsapp) whatsapp.value = onlyDigits(whatsapp.value).slice(0,11);
        if (cep)      cep.value      = onlyDigits(cep.value).slice(0,8);
        if (email)    email.value    = (email.value || '').trim().toLowerCase();

        // validações básicas antes de enviar
        if (cpf && !cpfIsValid(cpf.value)) {
        e.preventDefault();
        notify({icon:'warning', title:'CPF inválido', text:'Verifique os dígitos do CPF informado.'});
        return;
        }
        if (whatsapp && whatsapp.value && !whatsappIsValid(whatsapp.value)) {
        e.preventDefault();
        notify({icon:'warning', title:'WhatsApp inválido', text:'Informe DDD + número (10 ou 11 dígitos).'});
        return;
        }
        if (cep && cep.value && !cepIsValid(cep.value)) {
        e.preventDefault();
        notify({icon:'warning', title:'CEP inválido', text:'Use 8 dígitos, ex.: 57084056.'});
        return;
        }

        setSubmitting(true);
        // proteção se usuário tentar sair durante o envio
        window.addEventListener('beforeunload', beforeUnloadHandler);
    });

    function beforeUnloadHandler (e) {
        if (form.dataset.submitted === '1') {
        e.preventDefault();
        e.returnValue = '';
        return '';
        }
    }
    })();
    </script>

</x-app-layout>
