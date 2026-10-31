    {{-- Hero — satu layar penuh --}}
    <section class="relative flex min-h-screen items-center overflow-hidden bg-ocean-600 pb-24 pt-28 text-white sm:pt-32">
        {{-- Gambar hero sebagai latar penuh, dibuat sedikit transparan dan melebur ke warna section --}}
        <img
            src="{{ asset('img/hero.webp') }}"
            alt=""
            aria-hidden="true"
            width="1536" height="1024"
            loading="eager" fetchpriority="high" decoding="async"
            class="pointer-events-none absolute inset-0 h-full w-full select-none object-cover opacity-60"
        >
        {{-- Overlay warna bg di atas gambar agar menyatu dan teks tetap terbaca.
             Kiri hampir tertutup penuh (area teks judul), kanan dibiarkan jernih agar gambar terlihat. --}}
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-ocean-600/82 from-40% via-ocean-600/52 via-65% to-transparent" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-ocean-600/45 via-transparent to-ocean-600/10" aria-hidden="true"></div>

        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-32 -right-24 h-96 w-96 rounded-full bg-ocean-300/30 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(165,201,202,0.35),transparent_55%)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(231,246,242,0.18),transparent_55%)]"></div>
        </div>

        <div class="relative mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <span class="reveal inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-widest text-white backdrop-blur">
                    <x-heroicon-o-shield-check class="h-4 w-4" />
                    Kartu Izin Masuk Digital 
                </span>

                <h1 class="reveal text-balance-heading mt-6 text-4xl font-bold leading-[1.1] tracking-tight text-white sm:text-5xl lg:text-6xl" style="--reveal-delay:80ms">
                    O-KIM Lebih Cepat,<br>
                    <span class="bg-gradient-to-r from-white to-ocean-200 bg-clip-text text-transparent">Rapi & Terkendali.</span>
                </h1>

                <p class="reveal mt-6 max-w-xl text-lg leading-relaxed text-ocean-100" style="--reveal-delay:160ms">
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

                <div class="reveal mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-ocean-100" style="--reveal-delay:320ms">
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-check-circle class="h-5 w-5 text-ocean-200" /> Daftar via email atau Google</span>
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-check-circle class="h-5 w-5 text-ocean-200" /> Tanpa berkas fisik</span>
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-check-circle class="h-5 w-5 text-ocean-200" /> Validasi QR Code</span>
                </div>
            </div>
        </div>

        {{-- Scroll cue --}}
        <a href="#layanan" aria-label="Gulir ke bawah"
           class="absolute inset-x-0 bottom-6 mx-auto hidden w-fit flex-col items-center gap-1.5 text-white/80 transition hover:text-white sm:flex">
            <span class="text-[11px] font-medium uppercase tracking-widest">Gulir</span>
            <span class="flex h-9 w-6 items-start justify-center rounded-full border border-current p-1">
                <span class="h-2 w-1 animate-bounce rounded-full bg-current"></span>
            </span>
        </a>
    </section>
