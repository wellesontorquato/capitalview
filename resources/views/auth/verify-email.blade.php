<x-guest-layout>
  <div class="rounded-3xl border border-slate-200/70 bg-white/95 backdrop-blur shadow-xl
              p-6 sm:p-8 md:p-10 w-full max-w-md mx-auto">
    <header class="text-center space-y-2 mb-6 md:mb-8">
      <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900">Verifique seu e-mail</h1>
      <p class="text-sm md:text-base text-slate-600 leading-relaxed">
        Obrigado por se cadastrar! Antes de começar, confirme seu endereço de e-mail clicando no link que acabamos de enviar.
        <br>
        Caso não tenha recebido, podemos enviar outro para você.
      </p>
    </header>

    {{-- Mensagem de sucesso ao reenviar --}}
    @if (session('status') == 'verification-link-sent')
      <div class="mb-6 p-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm font-medium border border-emerald-200">
        Um novo link de verificação foi enviado para o e-mail informado no cadastro.
      </div>
    @endif

    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
      {{-- Reenviar link --}}
      <form method="POST" action="{{ route('verification.send') }}" class="w-full sm:w-auto">
        @csrf
        <x-primary-button class="w-full sm:w-auto h-12 justify-center rounded-2xl text-base tracking-wide">
          Reenviar e-mail de verificação
        </x-primary-button>
      </form>

      {{-- Logout --}}
      <form method="POST" action="{{ route('logout') }}" class="w-full sm:w-auto">
        @csrf
        <button type="submit"
                class="w-full sm:w-auto text-sm font-medium text-slate-600 hover:text-slate-800
                       underline underline-offset-2 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500">
          Sair
        </button>
      </form>
    </div>

    <p class="mt-8 text-center text-xs text-slate-500">© {{ date('Y') }} — Todos os direitos reservados.</p>
  </div>
</x-guest-layout>
