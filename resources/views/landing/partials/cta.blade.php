    {{-- CTA --}}
    <section class="bg-pastel-soft py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="reveal relative overflow-hidden rounded-3xl border border-lavender-200 bg-pastel px-8 py-14 text-center shadow-xl shadow-lavender-900/10 sm:px-16">
                <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-mint-300/50 blur-3xl" aria-hidden="true"></div>
                <div class="relative">
                    {{-- <img src="{{ asset('img/logo.png') }}" alt="" class="mx-auto h-12 w-auto brightness-0 invert"> --}}
                    <h2 class="text-balance-heading mt-6 text-3xl font-bold text-lavender-900 sm:text-4xl">Mulai pengajuan KIM Anda hari ini</h2>
                    <p class="mx-auto mt-4 max-w-xl text-lavender-700">Cukup dengan akun Google. Tanpa antre, tanpa berkas berlapis.</p>
                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        @auth
                            <x-landing.button href="{{ auth()->user()->homeUrl() }}" variant="primary" size="lg">
                                <x-heroicon-o-squares-2x2 class="h-5 w-5" />
                                {{ auth()->user()->hasOperationalAccess() ? 'Buka Panel Saya' : 'Lanjutkan Pendaftaran' }}
                            </x-landing.button>
                        @else
                            <x-landing.button href="{{ route('register') }}" variant="primary" size="lg">
                                <x-heroicon-o-user-plus class="h-5 w-5" />
                                Buat Akun Sekarang
                            </x-landing.button>
                        @endauth
                        <x-landing.button href="#persyaratan" variant="secondary" size="lg">Lihat Persyaratan</x-landing.button>
                    </div>
                </div>
            </div>
        </div>
    </section>
