<x-guest-layout :title="config('app.name') . ' - Criar conta'">
  <div class="rounded-3xl border border-slate-200/70 bg-white/95 backdrop-blur shadow-xl
              p-6 sm:p-8 md:p-10 w-full max-w-md mx-auto">
    <header class="text-center space-y-2 mb-6 md:mb-8">
      <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900">Criar conta</h1>
      <p class="text-sm md:text-base text-slate-600">Preencha seus dados para começar.</p>
    </header>

    <form method="POST" action="{{ route('register') }}" class="space-y-6 md:space-y-8">
      @csrf

      {{-- Nome --}}
      <div class="space-y-2">
        <x-input-label for="name" value="Nome completo" class="text-slate-700"/>
        <x-text-input id="name" name="name" type="text" :value="old('name')" required autofocus autocomplete="name"
                      placeholder="Seu nome completo"
                      class="block w-full h-12 rounded-2xl border-slate-300 bg-white
                             text-slate-900 placeholder-slate-400
                             focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
        <x-input-error :messages="$errors->get('name')" />
      </div>

      {{-- E-mail --}}
      <div class="space-y-2">
        <x-input-label for="email" value="E-mail" class="text-slate-700"/>
        <x-text-input id="email" name="email" type="email" :value="old('email')" required autocomplete="username"
                      placeholder="voce@exemplo.com"
                      class="block w-full h-12 rounded-2xl border-slate-300 bg-white
                             text-slate-900 placeholder-slate-400
                             focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
        <x-input-error :messages="$errors->get('email')" />
      </div>

      {{-- CPF (obrigatório, máscara opcional) --}}
      <div class="space-y-2">
        <x-input-label for="cpf" value="CPF" class="text-slate-700"/>
        <x-text-input
            id="cpf"
            name="cpf"
            type="text"
            :value="old('cpf')"
            inputmode="numeric"
            autocomplete="off"
            required
            placeholder="000.000.000-00"
            maxlength="14"
            pattern="^(?:\d{11}|\d{3}\.\d{3}\.\d{3}-\d{2})$"
            title="Digite 11 dígitos ou no formato 000.000.000-00"
            class="block w-full h-12 rounded-2xl border-slate-300 bg-white
                  text-slate-900 placeholder-slate-400
                  focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
        />
        <x-input-error :messages="$errors->get('cpf')" />
      </div>

      {{-- Senha --}}
      <div class="space-y-2">
        <x-input-label for="password" value="Senha" class="text-slate-700"/>
        <x-text-input id="password" name="password" type="password" required autocomplete="new-password"
                      placeholder="••••••••"
                      class="block w-full h-12 rounded-2xl border-slate-300 bg-white
                             text-slate-900 placeholder-slate-400
                             focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
        <x-input-error :messages="$errors->get('password')" />
      </div>

      {{-- Confirmar Senha --}}
      <div class="space-y-2">
        <x-input-label for="password_confirmation" value="Confirmar senha" class="text-slate-700"/>
        <x-text-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                      placeholder="Repita a senha"
                      class="block w-full h-12 rounded-2xl border-slate-300 bg-white
                             text-slate-900 placeholder-slate-400
                             focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
        <x-input-error :messages="$errors->get('password_confirmation')" />
      </div>

      {{-- Ações --}}
      <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <a href="{{ route('login') }}"
           class="text-sm font-medium text-indigo-600 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">
          Já possui conta?
        </a>

        <x-primary-button class="w-full sm:w-auto h-12 rounded-2xl px-6">
          Criar conta
        </x-primary-button>
      </div>
    </form>

    <p class="mt-8 text-center text-xs text-slate-500">© {{ date('Y') }} — Todos os direitos reservados.</p>
  </div>

  {{-- Máscara CPF (inline, sem depender de @push/@stack) --}}
  <script>
  (function () {
    var input = document.getElementById('cpf');
    if (!input) return;
    input.addEventListener('input', function (e) {
      var v = (e.target.value || '').replace(/\D/g, '').slice(0, 11);
      var out = '';
      if (v.length > 0) out = v.substring(0,3);
      if (v.length >= 4) out += '.' + v.substring(3,6);
      if (v.length >= 7) out += '.' + v.substring(6,9);
      if (v.length >= 10) out += '-' + v.substring(9,11);
      e.target.value = out;

      // HTML5 validity: válido somente com 11 dígitos (mascarado ou não)
      if (v.length === 11) e.target.setCustomValidity('');
      else e.target.setCustomValidity('Informe um CPF com 11 dígitos.');
    });
  })();
  </script>
</x-guest-layout>
