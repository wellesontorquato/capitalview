<x-guest-layout>
  <div class="rounded-3xl border border-slate-200/70 bg-white/95 backdrop-blur shadow-xl
              p-6 sm:p-8 md:p-10 w-full max-w-md mx-auto">
    <header class="text-center space-y-2 mb-6 md:mb-8">
      <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900">Confirmar senha</h1>
      <p class="text-sm md:text-base text-slate-600 leading-relaxed">
        Esta é uma área segura da aplicação.<br>
        Confirme sua senha antes de continuar.
      </p>
    </header>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-6 md:space-y-8">
      @csrf

      {{-- Senha --}}
      <div class="space-y-2">
        <x-input-label for="password" value="Senha" class="text-slate-700"/>
        <x-text-input id="password" name="password" type="password" required autocomplete="current-password"
                      placeholder="••••••••"
                      class="block w-full h-12 rounded-2xl border-slate-300 bg-white
                             text-slate-900 placeholder-slate-400
                             focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
        <x-input-error :messages="$errors->get('password')" />
      </div>

      {{-- Ação --}}
      <x-primary-button class="w-full h-12 justify-center rounded-2xl text-base tracking-wide">
        Confirmar
      </x-primary-button>
    </form>

    <p class="mt-8 text-center text-xs text-slate-500">© {{ date('Y') }} — Todos os direitos reservados.</p>
  </div>
</x-guest-layout>
