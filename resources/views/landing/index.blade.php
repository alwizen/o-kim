<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="O-KIM — sistem digital pendaftaran transportir, pemeriksaan kendaraan tangki, dan penerbitan Kartu Izin Masuk Fuel Terminal Tegal.">
    <title>O-KIM — Kartu Izin Masuk Fuel Terminal Tegal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white font-sans text-slate-800 antialiased selection:bg-navy-600 selection:text-white">

    {{-- Scroll progress --}}
    <div class="fixed inset-x-0 top-0 z-50 h-0.5 bg-transparent">
        <div data-scroll-progress class="h-full origin-left scale-x-0 bg-gradient-to-r from-navy-500 to-sky-400"></div>
    </div>

    {{-- Header --}}
    <header data-header
            class="group fixed inset-x-0 top-0 z-40 border-b border-transparent bg-transparent transition-all duration-300 data-[scrolled]:border-navy-100 data-[scrolled]:bg-white/90 data-[scrolled]:shadow-sm data-[scrolled]:backdrop-blur">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8" aria-label="Navigasi utama">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white p-1 shadow-sm ring-1 ring-black/5">
                    <img src="{{ asset('logo.png') }}" alt="Logo O-KIM" class="h-full w-full object-contain" width="40" height="40">
                </span>
                <span class="hidden flex-col leading-none sm:flex">
                    <span class="text-base font-bold tracking-tight text-white transition-colors group-data-[scrolled]:text-navy-800">O-KIM</span>
                    <span class="text-[11px] font-medium text-white/70 transition-colors group-data-[scrolled]:text-slate-500">Fuel Terminal Tegal</span>
                </span>
            </a>

            <div class="hidden items-center gap-1 lg:flex">
                <a href="#layanan" class="rounded-full px-4 py-2 text-sm font-medium text-white/80 transition hover:bg-white/10 hover:text-white group-data-[scrolled]:text-slate-600 group-data-[scrolled]:hover:bg-navy-50 group-data-[scrolled]:hover:text-navy-800">Layanan</a>
                <a href="#persyaratan" class="rounded-full px-4 py-2 text-sm font-medium text-white/80 transition hover:bg-white/10 hover:text-white group-data-[scrolled]:text-slate-600 group-data-[scrolled]:hover:bg-navy-50 group-data-[scrolled]:hover:text-navy-800">Persyaratan</a>
                <a href="#panduan" class="rounded-full px-4 py-2 text-sm font-medium text-white/80 transition hover:bg-white/10 hover:text-white group-data-[scrolled]:text-slate-600 group-data-[scrolled]:hover:bg-navy-50 group-data-[scrolled]:hover:text-navy-800">Panduan</a>
                <a href="#faq" class="rounded-full px-4 py-2 text-sm font-medium text-white/80 transition hover:bg-white/10 hover:text-white group-data-[scrolled]:text-slate-600 group-data-[scrolled]:hover:bg-navy-50 group-data-[scrolled]:hover:text-navy-800">FAQ</a>
            </div>

            <div class="hidden items-center gap-2 lg:flex">
                @auth
                    @if (auth()->user()->hasOperationalAccess())
                        <span class="hidden text-sm font-medium text-white/70 group-data-[scrolled]:text-slate-500 xl:inline">
                            {{ auth()->user()->name }}
                        </span>
                        <x-landing.button href="{{ auth()->user()->homeUrl() }}" size="sm">
                            <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                            Buka Panel
                        </x-landing.button>
                    @else
                        <x-landing.button href="{{ auth()->user()->homeUrl() }}" size="sm">
                            <x-heroicon-o-clipboard-document-check class="h-4 w-4" />
                            Lanjutkan Pendaftaran
                        </x-landing.button>
                    @endif
                @else
                    <a href="{{ route('login.form') }}" class="rounded-full px-4 py-2 text-sm font-semibold text-white/90 transition hover:bg-white/10 hover:text-white group-data-[scrolled]:text-navy-700 group-data-[scrolled]:hover:bg-navy-50">Masuk</a>
                    <x-landing.button href="{{ route('register') }}" size="sm">
                        <x-heroicon-o-arrow-right-end-on-rectangle class="h-4 w-4" />
                        Daftar
                    </x-landing.button>
                @endauth
            </div>

            <button type="button" data-menu-toggle aria-expanded="false" aria-label="Buka menu"
                    class="inline-flex items-center justify-center rounded-lg border border-white/25 bg-white/10 p-2 text-white backdrop-blur transition hover:bg-white/20 group-data-[scrolled]:border-navy-100 group-data-[scrolled]:bg-white/80 group-data-[scrolled]:text-navy-700 group-data-[scrolled]:hover:bg-navy-50 lg:hidden">
                <x-heroicon-o-bars-3 class="h-5 w-5" />
            </button>
        </nav>

        {{-- Mobile menu --}}
        <div data-mobile-menu class="hidden border-t border-navy-100 bg-white px-4 pb-5 pt-3 shadow-lg lg:hidden">
            <div class="flex flex-col gap-1">
                <a href="#layanan" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-navy-50">Layanan</a>
                <a href="#persyaratan" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-navy-50">Persyaratan</a>
                <a href="#panduan" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-navy-50">Panduan</a>
                <a href="#faq" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-navy-50">FAQ</a>
                @auth
                    <div class="mt-2 rounded-xl bg-navy-50 p-4">
                        <p class="text-xs text-slate-500">Masuk sebagai</p>
                        <p class="truncate text-sm font-semibold text-navy-800">{{ auth()->user()->name }}</p>
                        <x-landing.button href="{{ auth()->user()->homeUrl() }}" class="mt-3 w-full">
                            <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                            {{ auth()->user()->hasOperationalAccess() ? 'Buka Panel' : 'Lanjutkan Pendaftaran' }}
                        </x-landing.button>
                    </div>
                @else
                    <a href="{{ route('login.form') }}" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-navy-50">Masuk</a>
                    <x-landing.button href="{{ route('register') }}" class="mt-2 w-full">
                        <x-heroicon-o-user-plus class="h-4 w-4" />
                        Buat Akun
                    </x-landing.button>
                @endauth
            </div>
        </div>
    </header>

    @if (session('error'))
        <div class="fixed inset-x-0 top-20 z-50 mx-auto max-w-3xl px-4">
            <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-lg">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0" />
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- Hero — satu layar penuh --}}
    <section class="relative flex min-h-screen items-center overflow-hidden bg-navy-900 pb-24 pt-28 text-white sm:pt-32">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-32 -right-24 h-96 w-96 rounded-full bg-sky-500/20 blur-3xl"></div>
            <div class="absolute bottom-0 -left-24 h-80 w-80 rounded-full bg-navy-500/30 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(56,189,248,0.12),transparent_45%)]"></div>
            <svg class="absolute inset-0 h-full w-full opacity-[0.06]" aria-hidden="true">
                <defs>
                    <pattern id="grid" width="48" height="48" patternUnits="userSpaceOnUse">
                        <path d="M 48 0 L 0 0 0 48" fill="none" stroke="white" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)"/>
            </svg>
        </div>

        <div class="relative mx-auto grid w-full max-w-7xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-2 lg:gap-10 lg:px-8">
            <div>
                <span class="reveal inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-widest text-sky-100 backdrop-blur">
                    <x-heroicon-o-shield-check class="h-4 w-4" />
                    Online - Kendaraan Izin Masuk (O-KIM)
                </span>

                <h1 class="reveal text-balance-heading mt-6 text-4xl font-bold leading-[1.1] tracking-tight sm:text-5xl lg:text-6xl" style="--reveal-delay:80ms">
                    O-KIM Lebih Cepat,<br>
                    <span class="bg-gradient-to-r from-sky-300 to-white bg-clip-text text-transparent">Rapi & Terkendali.</span>
                </h1>

                <p class="reveal mt-6 max-w-xl text-lg leading-relaxed text-navy-100" style="--reveal-delay:160ms">
                     O-KIM adalah digitalisasi proses pembuatan Kartu Izin Masuk mobil tangki di Fuel Terminal Tegal. Mulai dari input data general kendaraan, pengajuan pemeriksaan, checklist per fungsi, tindak lanjut temuan, approval paralel, sampai kartu KIM terbit dengan QR validasi dan masa berlaku mengikuti dokumen yang paling cepat habis.
                </p>

                <div class="reveal mt-9 flex flex-col gap-3 sm:flex-row sm:items-center" style="--reveal-delay:240ms">
                    @auth
                        <x-landing.button href="{{ auth()->user()->homeUrl() }}" variant="light" size="lg">
                            <x-heroicon-o-squares-2x2 class="h-5 w-5" />
                            {{ auth()->user()->hasOperationalAccess() ? 'Buka Panel Saya' : 'Lanjutkan Pendaftaran' }}
                        </x-landing.button>
                    @else
                        <x-landing.button href="{{ route('register') }}" variant="light" size="lg">
                            <x-heroicon-o-arrow-right-end-on-rectangle class="h-5 w-5" />
                            Mulai Pendaftaran
                        </x-landing.button>
                    @endauth
                    <x-landing.button href="#panduan" variant="outline-light" size="lg">
                        Lihat Panduan
                        <x-heroicon-o-arrow-down class="h-4 w-4" />
                    </x-landing.button>
                </div>

                <div class="reveal mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-navy-200" style="--reveal-delay:320ms">
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-check-circle class="h-5 w-5 text-sky-300" /> Daftar via email atau Google</span>
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-check-circle class="h-5 w-5 text-sky-300" /> Tanpa berkas fisik</span>
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-check-circle class="h-5 w-5 text-sky-300" /> Validasi QR Code</span>
                </div>
            </div>

            {{-- Hero card mock --}}
            <div class="reveal relative" style="--reveal-delay:200ms">
                <div class="relative mx-auto max-w-sm">
                    <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-tr from-sky-500/20 to-transparent blur-2xl"></div>
                    <div class="relative overflow-hidden rounded-2xl border border-white/15 bg-white/95 p-6 shadow-2xl shadow-navy-950/40 backdrop-blur">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <img src="{{ asset('logo.png') }}" alt="" class="h-8 w-auto">
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Aktif
                            </span>
                        </div>

                        <p class="mt-5 text-xs font-semibold uppercase tracking-widest text-slate-400">Nomor KIM</p>
                        <p class="mt-1 font-mono text-lg font-bold text-navy-900">KIM/FT-TGL/2026/10/0001</p>

                        <div class="mt-5 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-xs text-slate-400">NOPOL</p>
                                <p class="font-semibold text-navy-800">G 1234 TGL</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400">Berlaku Hingga</p>
                                <p class="font-semibold text-navy-800">14 Jun 2027</p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center gap-4 rounded-xl bg-green-100 p-4">
                            <x-ionicon-qr-code-sharp class="text-gray-800" />
                            <p class="text-xs leading-relaxed text-slate-500">Pindai QR pada kartu untuk memverifikasi keaslian dan masa berlaku KIM.</p>
                        </div>
                    </div>

                    <div class="absolute -bottom-5 -left-5 hidden rounded-xl border border-navy-100 bg-white px-4 py-3 shadow-xl sm:block">
                        <p class="text-xs text-slate-400">Pengajuan diproses</p>
                        <p class="text-lg font-bold text-navy-800">3 fungsi &middot; 1 alur</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Scroll cue --}}
        <a href="#layanan" aria-label="Gulir ke bawah"
           class="absolute inset-x-0 bottom-6 mx-auto hidden w-fit flex-col items-center gap-1.5 text-white/50 transition hover:text-white/90 sm:flex">
            <span class="text-[11px] font-medium uppercase tracking-widest">Gulir</span>
            <span class="flex h-9 w-6 items-start justify-center rounded-full border border-current p-1">
                <span class="h-2 w-1 animate-bounce rounded-full bg-current"></span>
            </span>
        </a>
    </section>

    {{-- Stats strip --}}
    <section class="border-b border-navy-100 bg-white">
        <div class="mx-auto grid max-w-7xl grid-cols-2 divide-navy-100 px-4 sm:px-6 lg:grid-cols-4 lg:divide-x lg:px-8">
            @foreach ([
                ['icon' => 'o-user-group', 'value' => 'Transportir', 'label' => 'Kelola data & dokumen'],
                ['icon' => 'o-clipboard-document-check', 'value' => 'HSSE · Distribusi · QQ', 'label' => 'Pemeriksaan terpadu'],
                ['icon' => 'o-qr-code', 'value' => 'QR Code', 'label' => 'Validasi keaslian'],
                ['icon' => 'o-document-text', 'value' => 'Digital', 'label' => 'KIM siap unduh'],
            ] as $stat)
                <div class="reveal flex flex-col gap-2 px-2 py-8 text-center lg:px-8">
                    <x-dynamic-component :component="'heroicon-'.$stat['icon']" class="mx-auto h-6 w-6 text-navy-500" />
                    <p class="mt-1 text-base font-bold text-navy-800">{{ $stat['value'] }}</p>
                    <p class="text-sm text-slate-500">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Layanan --}}
    <section id="layanan" class="scroll-mt-24 bg-white py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-landing.section-heading
                eyebrow="Layanan O-KIM"
                title="Satu sistem untuk seluruh proses perizinan"
                description="Menggantikan alur manual dengan workflow digital yang terstruktur, transparan, dan tercatat."
            />

            <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $services = [
                        ['icon' => 'o-building-office-2', 'title' => 'Pendaftaran Transportir', 'desc' => 'Daftar via akun Google, lengkapi profil perusahaan, dan dapatkan verifikasi resmi dari admin.'],
                        ['icon' => 'o-truck', 'title' => 'Data Kendaraan & Dokumen', 'desc' => 'Kelola armada tangki beserta dokumen wajib seperti STNK, KEUR, dan TERA dengan riwayat versi.'],
                        ['icon' => 'o-clipboard-document-check', 'title' => 'Pemeriksaan Terpadu', 'desc' => 'HSSE, Distribusi, dan QQ memeriksa melalui checklist digital yang dapat dikelola tanpa ubah kode.'],
                        ['icon' => 'o-wrench-screwdriver', 'title' => 'Tindak Lanjut Temuan', 'desc' => 'Temuan ditindaklanjuti dengan bukti, lalu diverifikasi petugas sebelum dinyatakan selesai.'],
                        ['icon' => 'o-check-badge', 'title' => 'Approval Berjenjang', 'desc' => 'Persetujuan tiga fungsi, dilanjutkan approval final oleh Fuel Terminal Manager.'],
                        ['icon' => 'o-identification', 'title' => 'KIM Digital & QR Code', 'desc' => 'Kartu Izin Masuk terbit otomatis dengan nomor unik, masa berlaku, dan QR validasi publik.'],
                    ];
                @endphp

                @foreach ($services as $index => $service)
                    <article class="reveal group relative overflow-hidden rounded-2xl border border-navy-100 bg-white p-7 transition-all duration-300 hover:-translate-y-1 hover:border-navy-200 hover:shadow-xl hover:shadow-navy-900/5"
                             style="--reveal-delay:{{ ($index % 3) * 80 }}ms">
                        <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-navy-50 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                        <div class="relative flex h-12 w-12 items-center justify-center rounded-xl bg-navy-50 text-navy-600 transition-colors duration-300 group-hover:bg-navy-600 group-hover:text-white">
                            <x-dynamic-component :component="'heroicon-'.$service['icon']" class="h-6 w-6" />
                        </div>
                        <h3 class="relative mt-5 text-lg font-bold text-navy-900">{{ $service['title'] }}</h3>
                        <p class="relative mt-2 text-sm leading-relaxed text-slate-600">{{ $service['desc'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Persyaratan --}}
    <section id="persyaratan" class="scroll-mt-24 bg-navy-50/60 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-landing.section-heading
                eyebrow="Persyaratan"
                title="Siapkan berkas sebelum mengajukan"
                description="Pastikan data perusahaan dan dokumen kendaraan lengkap agar pengajuan dapat diproses tanpa hambatan."
            />

            <div class="mt-14 grid gap-6 lg:grid-cols-3">
                <div class="reveal rounded-2xl border border-navy-100 bg-white p-7">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-navy-700 text-sm font-bold text-white">1</span>
                        <h3 class="font-bold text-navy-900">Akun & Perusahaan</h3>
                    </div>
                    <ul class="mt-5 space-y-3 text-sm text-slate-600">
                        @foreach (['Akun Google aktif milik PIC', 'Nama dan alamat perusahaan', 'Nomor telepon perusahaan', 'Nomor identitas perusahaan (bila ada)'] as $item)
                            <li class="flex gap-3"><x-heroicon-o-check class="mt-0.5 h-5 w-5 shrink-0 text-navy-500" /> {{ $item }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="reveal rounded-2xl border border-navy-100 bg-white p-7" style="--reveal-delay:80ms">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-navy-700 text-sm font-bold text-white">2</span>
                        <h3 class="font-bold text-navy-900">Data Kendaraan</h3>
                    </div>
                    <ul class="mt-5 space-y-3 text-sm text-slate-600">
                        @foreach (['Nomor polisi (NOPOL) kendaraan tangki', 'Merek, model, dan tahun pembuatan', 'Kapasitas tangki', 'Data kendaraan tercatat rapi'] as $item)
                            <li class="flex gap-3"><x-heroicon-o-check class="mt-0.5 h-5 w-5 shrink-0 text-navy-500" /> {{ $item }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="reveal rounded-2xl border border-navy-100 bg-white p-7" style="--reveal-delay:160ms">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-navy-700 text-sm font-bold text-white">3</span>
                        <h3 class="font-bold text-navy-900">Dokumen Wajib</h3>
                    </div>
                    <ul class="mt-5 space-y-3 text-sm text-slate-600">
                        @foreach (['STNK — Surat Tanda Nomor Kendaraan', 'KEUR — Keterangan Uji Kendaraan', 'TERA — Surat Tera Tangki', 'PTO — bila dipersyaratkan'] as $item)
                            <li class="flex gap-3"><x-heroicon-o-check class="mt-0.5 h-5 w-5 shrink-0 text-navy-500" /> {{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <p class="reveal mx-auto mt-8 max-w-3xl text-center text-xs leading-relaxed text-slate-500">
                Format berkas yang diterima: PDF, JPG, atau PNG dengan ukuran maksimum 5 MB per dokumen.
                Daftar dokumen wajib dapat disesuaikan oleh admin melalui master data.
            </p>
        </div>
    </section>

    {{-- Panduan --}}
    <section id="panduan" class="scroll-mt-24 bg-white py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-14 lg:grid-cols-2 lg:items-center lg:gap-20">
                <div>
                    <x-landing.section-heading align="left" eyebrow="Panduan" title="Empat langkah menuju KIM terbit" />

                    <ol class="mt-10 space-y-8">
                        @php
                            $steps = [
                                ['title' => 'Daftar akun', 'desc' => 'Gunakan email & kata sandi atau akun Google perusahaan. Akun baru berstatus menunggu verifikasi.'],
                                ['title' => 'Lengkapi profil perusahaan', 'desc' => 'Isi data perusahaan transportir Anda. Admin akan memeriksa dan menyetujui pendaftaran.'],
                                ['title' => 'Ajukan kendaraan & dokumen', 'desc' => 'Tambahkan kendaraan tangki, unggah dokumen wajib, lalu kirim pengajuan untuk diperiksa.'],
                                ['title' => 'Terima KIM digital', 'desc' => 'Setelah seluruh pemeriksaan dan approval selesai, KIM terbit dan dapat diunduh beserta QR validasi.'],
                            ];
                        @endphp

                        @foreach ($steps as $index => $step)
                            <li class="reveal relative flex gap-5" style="--reveal-delay:{{ $index * 90 }}ms">
                                <div class="flex flex-col items-center">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-navy-200 bg-white text-sm font-bold text-navy-700">{{ $index + 1 }}</span>
                                    @if (! $loop->last)
                                        <span class="mt-1 h-full w-px flex-1 bg-gradient-to-b from-navy-200 to-transparent"></span>
                                    @endif
                                </div>
                                <div class="pb-2">
                                    <h3 class="text-base font-bold text-navy-900">{{ $step['title'] }}</h3>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $step['desc'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="reveal relative" style="--reveal-delay:120ms">
                    <div class="rounded-3xl border border-navy-100 bg-gradient-to-br from-navy-800 to-navy-950 p-8 text-white shadow-2xl shadow-navy-900/20 sm:p-10">
                        <x-heroicon-o-sparkles class="h-8 w-8 text-sky-300" />
                        <h3 class="mt-5 text-2xl font-bold">Pantau status, kapan saja</h3>
                        <p class="mt-3 text-sm leading-relaxed text-navy-100">
                            Setiap perubahan status tercatat dan dapat ditelusuri. Anda akan tahu persis
                            di tahap mana pengajuan berada — pemeriksaan, tindak lanjut, atau approval.
                        </p>

                        <div class="mt-8 space-y-4">
                            @foreach ([
                                ['label' => 'Dikirim', 'state' => 'done'],
                                ['label' => 'Pemeriksaan HSSE · Distribusi · QQ', 'state' => 'done'],
                                ['label' => 'Approval Final FTM', 'state' => 'current'],
                                ['label' => 'KIM Terbit', 'state' => 'pending'],
                            ] as $track)
                                <div class="flex items-center gap-3">
                                    @if ($track['state'] === 'done')
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-sky-400 text-navy-950"><x-heroicon-o-check class="h-4 w-4" /></span>
                                    @elseif ($track['state'] === 'current')
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-sky-300 bg-transparent">
                                            <span class="h-2 w-2 animate-pulse rounded-full bg-sky-300"></span>
                                        </span>
                                    @else
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white/25"></span>
                                    @endif
                                    <span class="text-sm {{ $track['state'] === 'pending' ? 'text-navy-200/60' : 'text-white' }}">{{ $track['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="scroll-mt-24 bg-navy-50/60 py-20 sm:py-28">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <x-landing.section-heading eyebrow="FAQ" title="Pertanyaan yang sering diajukan" />

            <div class="mt-12 space-y-3">
                @php
                    $faqs = [
                        ['q' => 'Siapa yang dapat mendaftar O-KIM?', 'a' => 'Perusahaan transportir yang akan mengoperasikan kendaraan tangki di Fuel Terminal Tegal. Pendaftaran dilakukan oleh PIC menggunakan email & kata sandi atau akun Google perusahaan.'],
                        ['q' => 'Berapa lama proses verifikasi akun?', 'a' => 'Setelah profil perusahaan dilengkapi, admin akan memeriksa data Anda. Anda akan menerima notifikasi setelah pendaftaran disetujui atau ditolak.'],
                        ['q' => 'Apakah KIM masih berbentuk kartu fisik?', 'a' => 'KIM diterbitkan secara digital dalam bentuk PDF dengan nomor unik dan QR Code. Kartu dapat diunduh dan dicetak bila diperlukan.'],
                        ['q' => 'Bagaimana cara memverifikasi keaslian KIM?', 'a' => 'Pindai QR Code pada KIM. Anda akan diarahkan ke halaman validasi resmi yang menampilkan status terkini: aktif, kedaluwarsa, atau dicabut.'],
                        ['q' => 'Apa yang terjadi bila ditemukan temuan pemeriksaan?', 'a' => 'Pengajuan tidak dapat lanjut hingga temuan diselesaikan. Anda mengirim tindak lanjut beserta bukti, lalu petugas yang berwenang memverifikasinya.'],
                    ];
                @endphp

                @foreach ($faqs as $index => $faq)
                    <details class="reveal group rounded-xl border border-navy-100 bg-white p-5 open:shadow-md" style="--reveal-delay:{{ $index * 60 }}ms">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-navy-900">
                            {{ $faq['q'] }}
                            <x-heroicon-o-chevron-down class="h-5 w-5 shrink-0 text-navy-400 transition-transform duration-300 group-open:rotate-180" />
                        </summary>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="bg-white py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="reveal relative overflow-hidden rounded-3xl bg-gradient-to-br from-navy-800 to-navy-950 px-8 py-14 text-center shadow-2xl shadow-navy-900/20 sm:px-16">
                <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-sky-500/20 blur-3xl" aria-hidden="true"></div>
                <div class="relative">
                    <img src="{{ asset('logo.png') }}" alt="" class="mx-auto h-12 w-auto brightness-0 invert">
                    <h2 class="text-balance-heading mt-6 text-3xl font-bold text-white sm:text-4xl">Mulai pengajuan KIM Anda hari ini</h2>
                    <p class="mx-auto mt-4 max-w-xl text-navy-100">Cukup dengan akun Google. Tanpa antre, tanpa berkas berlapis.</p>
                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        @auth
                            <x-landing.button href="{{ auth()->user()->homeUrl() }}" variant="light" size="lg">
                                <x-heroicon-o-squares-2x2 class="h-5 w-5" />
                                {{ auth()->user()->hasOperationalAccess() ? 'Buka Panel Saya' : 'Lanjutkan Pendaftaran' }}
                            </x-landing.button>
                        @else
                            <x-landing.button href="{{ route('register') }}" variant="light" size="lg">
                                <x-heroicon-o-user-plus class="h-5 w-5" />
                                Buat Akun Sekarang
                            </x-landing.button>
                        @endauth
                        <x-landing.button href="#persyaratan" variant="outline-light" size="lg">Lihat Persyaratan</x-landing.button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-navy-100 bg-navy-50/60">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2 lg:col-span-2">
                    <div class="flex items-center gap-3">
                        <img src="{{ asset('logo.png') }}" alt="Logo O-KIM" class="h-10 w-auto">
                        <div class="flex flex-col leading-none">
                            <span class="text-base font-bold text-navy-800">O-KIM</span>
                            <span class="text-xs text-slate-500">Online Kartu Izin Masuk</span>
                        </div>
                    </div>
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-slate-600">
                        Sistem internal operasional untuk pengelolaan Kartu Izin Masuk kendaraan tangki
                        di Fuel Terminal Tegal.
                    </p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-navy-900">Navigasi</h3>
                    <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                        <li><a href="#layanan" class="transition hover:text-navy-700">Layanan</a></li>
                        <li><a href="#persyaratan" class="transition hover:text-navy-700">Persyaratan</a></li>
                        <li><a href="#panduan" class="transition hover:text-navy-700">Panduan</a></li>
                        <li><a href="#faq" class="transition hover:text-navy-700">FAQ</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-navy-900">Akses</h3>
                    <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                        @auth
                            <li><a href="{{ auth()->user()->homeUrl() }}" class="font-medium transition hover:text-navy-700">{{ auth()->user()->hasOperationalAccess() ? 'Buka Panel Saya' : 'Lanjutkan Pendaftaran' }}</a></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="transition hover:text-navy-700">Keluar</button>
                                </form>
                            </li>
                        @else
                            <li><a href="{{ route('register') }}" class="transition hover:text-navy-700">Pendaftaran Transportir</a></li>
                            <li><a href="{{ route('login.form') }}" class="transition hover:text-navy-700">Masuk ke Akun</a></li>
                        @endauth
                        <li><a href="/admin/login" class="transition hover:text-navy-700">Masuk Panel Admin</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-navy-100 pt-6 text-sm text-slate-500 sm:flex-row">
                <p>&copy; {{ date('Y') }} O-KIM Fuel Terminal Tegal. Seluruh hak dilindungi.</p>
                {{-- <p class="flex items-center gap-2">
                    <x-heroicon-o-lock-closed class="h-4 w-4" />
                    Sistem internal — akses terbatas
                </p> --}}
            </div>
        </div>
    </footer>

</body>
</html>
