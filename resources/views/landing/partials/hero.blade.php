    {{-- Hero — satu layar penuh --}}
    <section class="relative flex min-h-screen items-center overflow-hidden bg-pastel pb-24 pt-28 text-lavender-900 sm:pt-32">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-32 -right-24 h-96 w-96 rounded-full bg-mint-400/40 blur-3xl"></div>
            {{-- <div class="absolute bottom-0 -left-24 h-80 w-80 rounded-full bg-lavender-300/50 blur-3xl"></div> --}}
            <div
    class="pointer-events-none absolute inset-0
           bg-[radial-gradient(ellipse_at_bottom_left,rgba(207,199,245,0.45),transparent_55%)]"
></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(208,236,243,0.55),transparent_55%)]"></div>
            <svg class="absolute inset-0 h-full w-full opacity-[0.35]" aria-hidden="true">
                <defs>
                    <pattern id="grid" width="48" height="48" patternUnits="userSpaceOnUse">
                        <path d="M 48 0 L 0 0 0 48" fill="none" stroke="#cfc7f5" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)"/>
            </svg>
        </div>

        <div class="relative mx-auto grid w-full max-w-7xl items-center gap-14 px-4 sm:px-6 lg:grid-cols-2 lg:gap-10 lg:px-8">
            <div>
                <span class="reveal inline-flex items-center gap-2 rounded-full border border-lavender-200 bg-white/70 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-widest text-lavender-700 backdrop-blur">
                    <x-heroicon-o-shield-check class="h-4 w-4" />
                    Online - Kartu Izin Masuk (O-KIM)
                </span>

                <h1 class="reveal text-balance-heading mt-6 text-4xl font-bold leading-[1.1] tracking-tight text-lavender-900 sm:text-5xl lg:text-6xl" style="--reveal-delay:80ms">
                    O-KIM Lebih Cepat,<br>
                    <span class="bg-gradient-to-r from-lavender-600 to-mint-600 bg-clip-text text-transparent">Rapi & Terkendali.</span>
                </h1>

                <p class="reveal mt-6 max-w-xl text-lg leading-relaxed text-lavender-700" style="--reveal-delay:160ms">
                     O-KIM adalah digitalisasi proses pembuatan Kartu Izin Masuk mobil tangki di Fuel Terminal Tegal. Mulai dari input data general kendaraan, pengajuan pemeriksaan, checklist per fungsi, tindak lanjut temuan, approval paralel, sampai kartu KIM terbit dengan QR validasi dan masa berlaku mengikuti dokumen yang paling cepat habis.
                </p>

                <div class="reveal mt-9 flex flex-col gap-3 sm:flex-row sm:items-center" style="--reveal-delay:240ms">
                    @auth
                        <x-landing.button href="{{ auth()->user()->homeUrl() }}" variant="primary" size="lg">
                            <x-heroicon-o-squares-2x2 class="h-5 w-5" />
                            {{ auth()->user()->hasOperationalAccess() ? 'Buka Panel Saya' : 'Lanjutkan Pendaftaran' }}
                        </x-landing.button>
                    @else
                        <x-landing.button href="{{ route('register') }}" variant="primary" size="lg">
                            <x-heroicon-o-arrow-right-end-on-rectangle class="h-5 w-5" />
                            Mulai Pendaftaran
                        </x-landing.button>
                    @endauth
                    <x-landing.button href="#panduan" variant="secondary" size="lg">
                        Lihat Panduan
                        <x-heroicon-o-arrow-down class="h-4 w-4" />
                    </x-landing.button>
                </div>

                <div class="reveal mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-lavender-700" style="--reveal-delay:320ms">
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-check-circle class="h-5 w-5 text-mint-600" /> Daftar via email atau Google</span>
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-check-circle class="h-5 w-5 text-mint-600" /> Tanpa berkas fisik</span>
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-check-circle class="h-5 w-5 text-mint-600" /> Validasi QR Code</span>
                </div>
            </div>

            {{-- Hero card mock --}}
            <div class="reveal relative" style="--reveal-delay:200ms">
                <div class="relative mx-auto max-w-sm">
                    <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-tr from-lavender-300/40 to-mint-200/40 blur-2xl"></div>
                    <div class="relative overflow-hidden rounded-2xl border border-lavender-200 bg-white/95 p-6 shadow-2xl shadow-lavender-900/15 backdrop-blur">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <img src="{{ asset('img/logo.svg') }}" alt="" class="h-8 w-auto">
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Aktif
                            </span>
                        </div>

                        <p class="mt-5 text-xs font-semibold uppercase tracking-widest text-slate-400">Nomor KIM</p>
                        <p class="mt-1 font-mono text-lg font-bold text-lavender-900">KIM/FT-TGL/2026/10/0001</p>

                        <div class="mt-5 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-xs text-slate-400">NOPOL</p>
                                <p class="font-semibold text-lavender-800">G 1234 TGL</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400">Berlaku Hingga</p>
                                <p class="font-semibold text-lavender-800">14 Jun 2027</p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-center gap-4 rounded-xl bg-lavender-50 p-4">
                            {{-- <x-ionicon-qr-code-sharp class="text-gray-800" /> --}}
                            <img src="{{ asset('img/animate.svg') }}" alt="QR Code KIM" >
                            {{-- <p class="text-xs leading-relaxed text-slate-500">Pindai QR pada kartu untuk memverifikasi keaslian dan masa berlaku KIM.</p> --}}
                        </div>
                    </div>

                    {{-- <div class="absolute -bottom-5 -left-5 hidden rounded-xl border border-lavender-100 bg-white px-4 py-3 shadow-xl sm:block">
                        <p class="text-xs text-slate-400">Pengajuan diproses</p>
                        <p class="text-lg font-bold text-lavender-800">3 fungsi &middot; 1 alur</p>
                    </div> --}}
                </div>
            </div>
        </div>

        {{-- Scroll cue --}}
        <a href="#layanan" aria-label="Gulir ke bawah"
           class="absolute inset-x-0 bottom-6 mx-auto hidden w-fit flex-col items-center gap-1.5 text-lavender-500 transition hover:text-lavender-700 sm:flex">
            <span class="text-[11px] font-medium uppercase tracking-widest">Gulir</span>
            <span class="flex h-9 w-6 items-start justify-center rounded-full border border-current p-1">
                <span class="h-2 w-1 animate-bounce rounded-full bg-current"></span>
            </span>
        </a>
    </section>
