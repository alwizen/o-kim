@props(['title', 'subtitle' => null])

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — O-KIM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-slate-800 antialiased">

    <div class="grid min-h-screen lg:grid-cols-2">

        {{-- Brand panel --}}
        <aside class="relative hidden overflow-hidden bg-navy-900 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-sky-500/20 blur-3xl"></div>
                <div class="absolute bottom-0 -left-20 h-80 w-80 rounded-full bg-navy-500/30 blur-3xl"></div>
                <svg class="absolute inset-0 h-full w-full opacity-[0.05]" aria-hidden="true">
                    <defs>
                        <pattern id="auth-grid" width="48" height="48" patternUnits="userSpaceOnUse">
                            <path d="M 48 0 L 0 0 0 48" fill="none" stroke="white" stroke-width="1"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#auth-grid)"/>
                </svg>
            </div>

            <a href="{{ route('landing') }}" class="relative flex items-center gap-3">
                <img src="{{ asset('logo.png') }}" alt="Logo O-KIM" class="h-11 w-auto">
            </a>

            <div class="relative">
                <h2 class="text-balance-heading text-3xl font-bold leading-tight">
                    Kartu Izin Masuk kendaraan tangki, kini serba digital.
                </h2>
                <p class="mt-4 max-w-md text-navy-100">
                    Kelola profil perusahaan, data kendaraan, dokumen, hingga pengajuan KIM dalam satu sistem
                    yang terstruktur dan dapat ditelusuri.
                </p>

                <ul class="mt-8 space-y-3 text-sm text-navy-100">
                    @foreach ([
                        'Pendaftaran & verifikasi transportir',
                        'Pemeriksaan HSSE, Distribusi, dan QQ',
                        'Approval berjenjang hingga KIM terbit',
                    ] as $item)
                        <li class="flex items-center gap-3">
                            <x-heroicon-o-check-circle class="h-5 w-5 text-sky-300" />
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="relative text-xs text-navy-200/70">
                &copy; {{ date('Y') }} O-KIM Fuel Terminal Tegal
            </p>
        </aside>

        {{-- Form panel --}}
        <main class="flex flex-col justify-center px-5 py-10 sm:px-10 lg:px-16">
            <div class="mx-auto w-full max-w-md">

                <a href="{{ route('landing') }}" class="mb-8 flex items-center gap-3 lg:hidden">
                    <img src="{{ asset('logo.png') }}" alt="Logo O-KIM" class="h-10 w-auto">
                    <div class="flex flex-col leading-none">
                        <span class="text-base font-bold text-navy-800">O-KIM</span>
                        <span class="text-[11px] text-slate-500">Fuel Terminal Tegal</span>
                    </div>
                </a>

                <h1 class="text-2xl font-bold tracking-tight text-navy-900 sm:text-3xl">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $subtitle }}</p>
                @endif

                @if (session('error'))
                    <div class="mt-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0" />
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if (session('status'))
                    <div class="mt-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                        <x-heroicon-o-check-circle class="mt-0.5 h-5 w-5 shrink-0" />
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                <div class="mt-8">
                    {{ $slot }}
                </div>

                {{ $footer ?? '' }}
            </div>
        </main>
    </div>

</body>
</html>
