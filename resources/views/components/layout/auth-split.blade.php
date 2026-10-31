@props([
    'livewire' => null,
    'maxContentWidth' => null,
])

{{--
    Layout login panel admin: dua kolom seperti resources/views/components/landing/auth-layout.
    Panel kiri berisi brand, panel kanan berisi form Filament.

    Struktur <x-filament-panels::layout.base> wajib dipertahankan karena komponen itulah
    yang memuat aset panel (styles, scripts, nonce CSP, dark mode, dan @livewireStyles).

    app.css perlu dimuat eksplisit: palet `auth-*` dan utilitas `bg-pastel-auth` didefinisikan
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

        /* Tombol, border fokus, dan link memakai palet biru halaman auth, bukan merah Press.
           Memakai :root agar menang atas definisi preset Press dengan spesifisitas sama. */
        :root {
            --press-primary-50: #f2f8fd;
            --press-primary-100: #e3f1fa;
            --press-primary-200: #bde8f5;
            --press-primary-300: #8fcdea;
            --press-primary-400: #4988c4;
            --press-primary-500: #1c4d8d;
            --press-primary-600: #1c4d8d;
            --press-primary-700: #0f2854;
            --press-primary-800: #0f2854;
            --press-primary-900: #0a1c3d;
        }

        /* Halaman login selalu tampil terang.
           Desainnya memakai palet auth-* yang hanya punya varian terang. Saat mode
           gelap panel aktif, Filament menambahkan class `dark` pada <html> dan
           mewarnai teks menjadi putih di atas latar putih, sehingga tidak terbaca.
           Aturan di bawah mengunci halaman ini ke tampilan terang; panel admin
           sendiri tetap mendukung mode gelap. */

        /* Variabel Panel dipaksa ke nilai terang agar komponen Filament tidak
           memakai pasangan warna dark miliknya. */
        .auth-split {
            color-scheme: light !important;

            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --gray-950: #030712;
        }

        .auth-split,
        .auth-split .fi-simple-main {
            background-color: #ffffff !important;
            color: #1f2937 !important;
        }

        /* Judul dan subjudul. */
        .auth-split .fi-simple-header-heading,
        .auth-split .fi-simple-header-heading * {
            color: #0f2854 !important;
        }

        .auth-split .fi-simple-header-subheading {
            color: #4b5563 !important;
        }

        /* Label field form (class sebenarnya pada markup Filament). */
        .auth-split .fi-fo-field-label,
        .auth-split .fi-fo-field-label-content,
        .auth-split .fi-fo-field-label-content span,
        .auth-split .fi-fo-field-label-ctn,
        .auth-split .fi-fo-field-wrp-label,
        .auth-split label {
            color: #1f2937 !important;
        }

        /* Penanda wajib tetap berwarna merah tua agar terbaca. */
        .auth-split .fi-fo-field-label-content .fi-fo-field-label-required-mark,
        .auth-split .fi-required-mark {
            color: #b03626 !important;
        }

        /* Kotak input dan teks di dalamnya. */
        .auth-split .fi-input-wrp {
            background-color: #ffffff !important;
            box-shadow: inset 0 0 0 1px #d1d5db !important;
        }

        .auth-split .fi-input,
        .auth-split input,
        .auth-split textarea {
            background-color: transparent !important;
            color: #1f2937 !important;
        }

        .auth-split input::placeholder,
        .auth-split .fi-input::placeholder {
            color: #9ca3af !important;
        }

        /* Ikon di dalam input (mis. tombol lihat kata sandi). */
        .auth-split .fi-input-wrp .fi-icon,
        .auth-split .fi-input-wrp button {
            color: #6b7280 !important;
        }

        /* Teks "Ingat saya". */
        .auth-split .fi-fo-checkbox-list-option-label,
        .auth-split .fi-checkbox-label,
        .auth-split .fi-fo-checkbox-list label {
            color: #1f2937 !important;
        }

        /* Tautan bantuan (lupa kata sandi, kembali ke beranda). */
        .auth-split .fi-link,
        .auth-split .fi-link *,
        .auth-split a,
        .auth-split a * {
            color: #1c4d8d !important;
        }

        /* Panel brand kiri selalu memakai palet terang. */
        .auth-split aside,
        .auth-split aside * {
            color: #0f2854 !important;
        }
    </style>
@endpush

<x-filament-panels::layout.base :livewire="$livewire">
    {{-- auth-split dipakai sebagai pembungkus agar override warna di atas berlaku tanpa
         membawa gaya .auth-split milik Filament yang akan merusak grid dua kolom. --}}
    <div class="auth-split grid min-h-screen bg-white font-sans text-slate-800 antialiased lg:grid-cols-2">

        {{-- Brand panel — teks disamakan dengan halaman /masuk --}}
        <aside class="relative hidden overflow-hidden bg-pastel-auth p-12 text-auth-900 lg:flex lg:flex-col lg:justify-between">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-auth-400/40 blur-3xl"></div>
                <div class="absolute bottom-0 -left-20 h-80 w-80 rounded-full bg-auth-200/60 blur-3xl"></div>
                <svg class="absolute inset-0 h-full w-full opacity-[0.35]" aria-hidden="true">
                    <defs>
                        <pattern id="admin-auth-grid" width="48" height="48" patternUnits="userSpaceOnUse">
                            <path d="M 48 0 L 0 0 0 48" fill="none" stroke="#8fcdea" stroke-width="1"/>
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
                <p class="mt-4 max-w-md text-auth-700">
                    Verifikasi pendaftaran transportir, jalankan checklist per fungsi, tindak lanjuti temuan,
                    hingga approval berjenjang dalam satu sistem yang terstruktur dan dapat ditelusuri.
                </p>

                <ul class="mt-8 space-y-3 text-sm text-auth-700">
                    @foreach ([
                        'Verifikasi pendaftaran transportir',
                        'Pemeriksaan checklist per fungsi',
                        'Approval berjenjang hingga KIM terbit',
                    ] as $item)
                        <li class="flex items-center gap-3">
                            <x-heroicon-o-check-circle class="h-5 w-5 text-auth-600" />
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="relative text-xs text-auth-700">
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
                    <a href="{{ route('landing') }}" class="transition hover:text-auth-600">&larr; Kembali ke Halaman utama</a>
                </p>
            </div>
        </main>
    </div>
</x-filament-panels::layout.base>
