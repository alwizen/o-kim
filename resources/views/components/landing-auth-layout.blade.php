{{-- Layout ringan untuk halaman Panduan (/panduan) --}}
@props(['title', 'subtitle' => null])

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Panduan penggunaan O-KIM — dokumentasi resmi untuk setiap peran di Fuel Terminal Tegal.">
    <title>{{ $title }} — O-KIM</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('img/fav.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">

    {{-- Header --}}
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur-md">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex shrink-0 items-center gap-3">
                <img src="{{ asset('img/ppn.png') }}" alt="Logo O-KIM" class="h-9 w-auto">
                <span class="hidden flex-col leading-none sm:flex">
                    {{-- <span class="text-sm font-bold text-auth-900">O-KIM</span>
                    <span class="text-[11px] font-medium text-slate-500">Panduan Penggunaan</span> --}}
                </span>
            </a>

            <nav class="flex items-center gap-2">
                <a href="{{ route('panduan.index') }}"
                   @class([
                       'hidden rounded-lg px-3 py-2 text-sm font-medium transition sm:inline-flex',
                       'bg-auth-50 text-auth-700' => request()->routeIs('panduan.index'),
                       'text-slate-600 hover:bg-slate-100 hover:text-auth-700' => ! request()->routeIs('panduan.index'),
                   ])>
                    Semua Panduan
                </a>
                <a href="{{ route('landing') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-auth-300 hover:text-auth-700">
                    <x-heroicon-o-home class="h-4 w-4" />
                    <span class="hidden sm:inline">Halaman Utama</span>
                </a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 py-6 text-xs text-slate-500 sm:flex-row sm:px-6 lg:px-8">
            <p>&copy; {{ date('Y') }} O-KIM — Fuel Terminal Tegal</p>
            <p>Panduan Penggunaan</p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
