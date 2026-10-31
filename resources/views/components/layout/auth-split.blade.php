@props([
    'livewire' => null,
    'maxContentWidth' => null,
])

{{--
    Layout login panel admin: dua kolom seperti resources/views/components/landing/auth-layout.
    Panel kiri berisi brand, panel kanan berisi form Filament.

    Struktur <x-filament-panels::layout.base> wajib dipertahankan karena komponen itulah
    yang memuat aset panel (styles, scripts, nonce CSP, dark mode, dan @livewireStyles).

    app.css perlu dimuat eksplisit: palet `ocean-*` dan utilitas `bg-pastel` didefinisikan
    di sana, dan panel Filament tidak memuatnya secara default.
--}}
@push('styles')
    @vite('resources/css/app.css')
    <style>
        /* Di layar besar logo sudah tampil di panel brand kiri, jadi salinannya
           di form disembunyikan. Di mobile panel brand tidak tampil sehingga
           logo di header tetap diperlukan. */
        @media (min-width: 1024px) {
            .fi-simple-header .fi-logo,
            .fi-simple-header img.fi-logo {
                display: none;
            }
        }

        /* Tombol, border fokus, dan link memakai palet landing (ocean), bukan merah Press.
           Memakai :root agar menang atas definisi preset Press dengan spesifisitas sama. */
        :root {
            --press-primary-50: #f3fbf9;
            --press-primary-100: #e7f6f2;
            --press-primary-200: #c6dfde;
            --press-primary-300: #a5c9ca;
            --press-primary-400: #6f929b;
            --press-primary-500: #54767f;
            --press-primary-600: #32474b;
            --press-primary-700: #2c3333;
            --press-primary-800: #222727;
            --press-primary-900: #161b1b;
        }
    </style>
@endpush

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="grid min-h-screen bg-white font-sans text-slate-800 antialiased lg:grid-cols-2">

        {{-- Brand panel — teks disamakan dengan halaman /masuk --}}
        <aside class="relative hidden overflow-hidden bg-pastel p-12 text-ocean-900 lg:flex lg:flex-col lg:justify-between">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-ocean-400/40 blur-3xl"></div>
                <div class="absolute bottom-0 -left-20 h-80 w-80 rounded-full bg-ocean-300/50 blur-3xl"></div>
                <svg class="absolute inset-0 h-full w-full opacity-[0.35]" aria-hidden="true">
                    <defs>
                        <pattern id="admin-auth-grid" width="48" height="48" patternUnits="userSpaceOnUse">
                            <path d="M 48 0 L 0 0 0 48" fill="none" stroke="#a5c9ca" stroke-width="1"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#admin-auth-grid)"/>
                </svg>
            </div>

            <a href="{{ route('landing') }}" class="relative flex items-center gap-3">
                <img src="{{ asset('img/ppn.png') }}" alt="Logo O-KIM" class="h-11 w-auto">
            </a>

            <div class="relative">
                <h2 class="text-balance-heading text-3xl font-bold leading-tight">
                    Kartu Izin Masuk Mobil Tangki, kini serba digital.
                </h2>
                <p class="mt-4 max-w-md text-ocean-700">
                    Verifikasi pendaftaran transportir, jalankan checklist per fungsi, tindak lanjuti temuan,
                    hingga approval berjenjang dalam satu sistem yang terstruktur dan dapat ditelusuri.
                </p>

                <ul class="mt-8 space-y-3 text-sm text-ocean-700">
                    @foreach ([
                        'Verifikasi pendaftaran transportir',
                        'Pemeriksaan checklist per fungsi',
                        'Approval berjenjang hingga KIM terbit',
                    ] as $item)
                        <li class="flex items-center gap-3">
                            <x-heroicon-o-check-circle class="h-5 w-5 text-ocean-600" />
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="relative text-xs text-ocean-700">
                &copy; {{ date('Y') }} O-KIM Fuel Terminal Tegal
            </p>
        </aside>

        {{-- Form panel --}}
        <main class="flex flex-col justify-center px-5 py-10 sm:px-10 lg:px-16">
            <div class="mx-auto w-full max-w-md">

                {{-- Heading, subjudul, dan logo mobile dirender oleh halaman simple Filament; jangan diulang di sini. --}}
                <div class="mt-8">
                    {{ $slot }}
                </div>

                <p class="mt-8 text-center text-xs text-slate-400">
                    <a href="{{ route('landing') }}" class="transition hover:text-ocean-600">&larr; Kembali ke Halaman utama</a>
                </p>
            </div>
        </main>
    </div>
</x-filament-panels::layout.base>
