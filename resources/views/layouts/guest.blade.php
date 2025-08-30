{{-- resources/views/layouts/guest.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  {{-- Favicon --}}
  <link rel="icon" type="image/x-icon" href="{{ asset('assets/favicon.ico') }}">

  {{-- Título dinâmico (padrão = Nome do app + Entrar) --}}
  <title>{{ $title ?? config('app.name', 'Laravel') . ' - Entrar' }}</title>

  @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen antialiased">
  <div class="min-h-screen flex items-center justify-center px-4
              bg-gradient-to-br from-slate-50 via-white to-slate-100">
    <div class="w-full max-w-sm sm:max-w-md lg:max-w-lg">
      {{ $slot }}
    </div>
  </div>
</body>
</html>
