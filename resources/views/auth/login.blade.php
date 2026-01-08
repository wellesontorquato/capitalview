<x-guest-layout>
  <div class="rounded-3xl border border-slate-200/70 bg-white/95 backdrop-blur shadow-xl
              p-6 sm:p-8 md:p-10">
    <header class="text-center space-y-2 mb-6 md:mb-8">
      <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900">Entrar</h1>
      <p class="text-sm md:text-base text-slate-600">Acesse sua conta para continuar.</p>
    </header>

    <form method="POST" action="{{ route('login') }}" x-data="{ show:false }" class="space-y-6 md:space-y-8">
      @csrf

      {{-- E-mail --}}
      <div class="space-y-2">
        <x-input-label for="email" value="E-mail" class="text-slate-700"/>
        <x-text-input id="email" name="email" type="email" autocomplete="username"
                      :value="old('email')" required autofocus
                      placeholder="voce@exemplo.com"
                      class="block w-full h-12 rounded-2xl
                             border-slate-300 bg-white text-slate-900
                             placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
        <x-input-error :messages="$errors->get('email')" />
      </div>

      {{-- Senha --}}
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <x-input-label for="password" value="Senha" class="text-slate-700"/>
          @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}"
               class="text-xs md:text-sm font-medium text-indigo-600 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">
              Esqueceu a senha?
            </a>
          @endif
        </div>

        <div class="relative">
          <x-text-input id="password" name="password"
                        x-bind:type="show ? 'text':'password'"
                        autocomplete="current-password" required
                        placeholder="••••••••"
                        class="block w-full h-12 pr-12 rounded-2xl
                               border-slate-300 bg-white text-slate-900
                               placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
          <button type="button" x-on:click="show=!show"
                  class="absolute inset-y-0 right-0 my-1 mr-2 inline-flex items-center justify-center
                         h-10 w-10 rounded-xl text-slate-500 hover:text-slate-700
                         focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  aria-label="Mostrar/ocultar senha">
            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 5 12 5c4.64 0 8.577 2.51 9.964 6.678.07.21.07.434 0 .644C20.577 16.49 16.64 19 12 19c-4.64 0-8.577-2.51-9.964-6.678z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M3.98 8.223A10.477 10.477 0 001.934 12C3.32 16.168 7.258 18.678 11.898 18.678c1.9 0 3.689-.425 5.28-1.182M9.88 9.88a3 3 0 104.243 4.243M6.1 6.1l11.8 11.8" />
            </svg>
          </button>
        </div>
        <x-input-error :messages="$errors->get('password')" />
      </div>

      {{-- Lembrar-me --}}
      <label for="remember_me" class="inline-flex items-center gap-2 select-none">
        <input id="remember_me" type="checkbox"
               class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
               name="remember">
        <span class="text-sm md:text-base text-slate-700">Lembrar de mim</span>
      </label>

      {{-- Botão --}}
      <x-primary-button class="w-full h-12 justify-center rounded-2xl text-base tracking-wide">
        Entrar
      </x-primary-button>

      {{-- Cadastro --}}
      @if (Route::has('register'))
        <p class="text-center text-sm md:text-base text-slate-600">
          Não tem conta?
          <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded">
            Cadastre-se
          </a>
        </p>
      @endif
    </form>

    <p class="mt-8 text-center text-xs text-slate-500">© {{ date('Y') }} — Todos os direitos reservados.</p>
  </div>
</x-guest-layout>
