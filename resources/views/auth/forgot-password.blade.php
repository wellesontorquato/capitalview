<x-guest-layout>
  <div class="rounded-3xl border border-slate-200/70 bg-white/95 backdrop-blur shadow-xl
              p-6 sm:p-8 md:p-10 w-full max-w-md mx-auto">
    <header class="text-center space-y-2 mb-6 md:mb-8">
      <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900">Recuperar senha</h1>
      <p class="text-sm md:text-base text-slate-600 leading-relaxed">
        Esqueceu sua senha? Sem problemas.<br>
        Informe seu e-mail e enviaremos um link para redefini-la.
      </p>
    </header>

    {{-- Status de sessão (link enviado, etc.) --}}
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-6 md:space-y-8">
      @csrf

      {{-- E-mail --}}
      <div class="space-y-2">
        <x-input-label for="email" value="E-mail" class="text-slate-700"/>
        <x-text-input id="email" name="email" type="email" :value="old('email')" required autofocus
                      placeholder="voce@exemplo.com"
                      class="block w-full h-12 rounded-2xl border-slate-300 bg-white
                             text-slate-900 placeholder-slate-400
                             focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
        <x-input-error :messages="$errors->get('email')" />
      </div>

      {{-- Botão --}}
      <x-primary-button class="w-full h-12 justify-center rounded-2xl text-base tracking-wide">
        Enviar link de redefinição
      </x-primary-button>
    </form>

    <p class="mt-8 text-center text-xs text-slate-500">© {{ date('Y') }} — Todos os direitos reservados.</p>
  </div>
</x-guest-layout>
