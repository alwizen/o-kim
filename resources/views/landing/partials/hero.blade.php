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
                    Online - Kartu Izin Masuk (O-KIM)
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

                    {{-- <div class="absolute -bottom-5 -left-5 hidden rounded-xl border border-navy-100 bg-white px-4 py-3 shadow-xl sm:block">
                        <p class="text-xs text-slate-400">Pengajuan diproses</p>
                        <p class="text-lg font-bold text-navy-800">3 fungsi &middot; 1 alur</p>
                    </div> --}}
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
