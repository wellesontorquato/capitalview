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
        <x-input-label for="name" value="Nome" class="text-slate-700"/>
        <x-text-input id="name" name="name" type="text" :value="old('name')" required autofocus autocomplete="name"
                      placeholder="Seu nome completo"
                      class="block w-full h-12 rounded-2xl border-slate-300 bg-white
                             text-slate-900 placeholder-slate-400
                             focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
        <x-input-error :messages="$errors->get('name')" />
      </div>

      {{-- Email --}}
      <div class="space-y-2">
        <x-input-label for="email" value="E-mail" class="text-slate-700"/>
        <x-text-input id="email" name="email" type="email" :value="old('email')" required autocomplete="username"
                      placeholder="voce@exemplo.com"
                      class="block w-full h-12 rounded-2xl border-slate-300 bg-white
                             text-slate-900 placeholder-slate-400
                             focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"/>
        <x-input-error :messages="$errors->get('email')" />
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
</x-guest-layout>
