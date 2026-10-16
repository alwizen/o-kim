    {{-- CTA --}}
    <section class="bg-white py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="reveal relative overflow-hidden rounded-3xl bg-gradient-to-br from-navy-800 to-navy-950 px-8 py-14 text-center shadow-2xl shadow-navy-900/20 sm:px-16">
                <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-sky-500/20 blur-3xl" aria-hidden="true"></div>
                <div class="relative">
                    {{-- <img src="{{ asset('logo.png') }}" alt="" class="mx-auto h-12 w-auto brightness-0 invert"> --}}
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
