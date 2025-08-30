<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? config('app.name', 'Carteira') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen antialiased bg-[#f7f9fc] text-slate-800">

    {{-- NAV / HEADER --}}
    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur border-b border-slate-200">
        <div class="container-p h-16 flex items-center justify-between gap-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 shrink-0">
                <div class="h-8 w-8 rounded-xl bg-brand-600"></div>
                <span class="font-extrabold text-ink-900">Carteira</span>
            </a>

            {{-- Desktop nav --}}
            <nav class="hidden sm:flex items-center gap-1">
                <a href="{{ route('clientes.index') }}"
                   class="px-3 py-2 rounded-lg hover:bg-slate-100 {{ request()->routeIs('clientes.*') ? 'text-brand-700 bg-brand-50' : '' }}">
                    Clientes
                </a>
                <a href="{{ route('emprestimos.index') }}"
                   class="px-3 py-2 rounded-lg hover:bg-slate-100 {{ request()->routeIs('emprestimos.*') ? 'text-brand-700 bg-brand-50' : '' }}">
                    Empréstimos
                </a>

                <div class="relative ml-2">
                    <details class="group">
                        <summary class="list-none flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-100 cursor-pointer">
                            <span class="hidden sm:inline text-slate-700">{{ Auth::user()->name }}</span>
                            <svg class="w-4 h-4 transition group-open:rotate-180" viewBox="0 0 24 24">
                                <path d="M6 9l6 6 6-6" stroke="#334155" stroke-width="2" fill="none"/>
                            </svg>
                        </summary>
                        <div class="absolute right-0 mt-2 w-52 card z-50">
                            <div class="card-p">
                                <a class="block px-3 py-2 rounded-lg hover:bg-slate-100" href="{{ route('profile.edit') }}">Perfil</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="block w-full text-left px-3 py-2 rounded-lg hover:bg-slate-100">Sair</button>
                                </form>
                            </div>
                        </div>
                    </details>
                </div>
            </nav>

            {{-- Mobile toggle --}}
            <button id="navToggle"
                    class="sm:hidden p-2 rounded-lg hover:bg-slate-100"
                    aria-label="Abrir menu"
                    aria-controls="mobileNav"
                    aria-expanded="false">
                <svg viewBox="0 0 24 24" class="w-6 h-6">
                    <path d="M4 6h16M4 12h16M4 18h16" stroke="#111827" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        {{-- Mobile nav --}}
        <div id="mobileNav" class="sm:hidden hidden border-t border-slate-200 bg-white">
            <div class="container-p py-2 space-y-1">
                <a class="block px-3 py-2 rounded-lg hover:bg-slate-100 {{ request()->routeIs('clientes.*') ? 'text-brand-700 bg-brand-50' : '' }}"
                   href="{{ route('clientes.index') }}">Clientes</a>
                <a class="block px-3 py-2 rounded-lg hover:bg-slate-100 {{ request()->routeIs('emprestimos.*') ? 'text-brand-700 bg-brand-50' : '' }}"
                   href="{{ route('emprestimos.index') }}">Empréstimos</a>
                <a class="block px-3 py-2 rounded-lg hover:bg-slate-100" href="{{ route('profile.edit') }}">Perfil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="w-full text-left px-3 py-2 rounded-lg hover:bg-slate-100">Sair</button>
                </form>
            </div>
        </div>

        <script>
            (function () {
                const btn = document.getElementById('navToggle');
                const menu = document.getElementById('mobileNav');
                btn?.addEventListener('click', () => {
                    const isHidden = menu.classList.toggle('hidden');
                    btn.setAttribute('aria-expanded', String(!isHidden));
                });
            })();
        </script>
    </header>

    {{-- Título/topo da página (slot opcional) --}}
    @isset($header)
        <section class="container-p pt-6">
            {{ $header }}
        </section>
    @endisset

    {{-- Conteúdo --}}
    <main class="container-p py-6">
        {{ $slot }}
    </main>

    @stack('scripts')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
