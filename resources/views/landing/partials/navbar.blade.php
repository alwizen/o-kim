    {{-- Header --}}
    <header data-header
            class="group fixed inset-x-0 top-0 z-40 border-b border-transparent bg-transparent transition-all duration-300 data-[scrolled]:border-lavender-100 data-[scrolled]:bg-white/85 data-[scrolled]:shadow-sm data-[scrolled]:backdrop-blur">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8" aria-label="Navigasi utama">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white p-1 shadow-sm ring-1 ring-black/5">
                    <img src="{{ asset('img/fav.svg') }}" alt="Logo O-KIM" class="h-full w-full object-contain" width="40" height="40">
                </span>
                <span class="flex flex-col leading-none">
                    <span class="text-base font-bold tracking-tight text-lavender-900">O-KIM</span>
                    <span class="text-[11px] font-medium text-lavender-600">Fuel Terminal Tegal</span>
                </span>
            </a>

            <div class="hidden items-center gap-1 lg:flex">
                <a href="#layanan" class="rounded-full px-4 py-2 text-sm font-medium text-lavender-700 transition hover:bg-white/70 hover:text-lavender-900">Layanan</a>
                <a href="#persyaratan" class="rounded-full px-4 py-2 text-sm font-medium text-lavender-700 transition hover:bg-white/70 hover:text-lavender-900">Persyaratan</a>
                <a href="#panduan" class="rounded-full px-4 py-2 text-sm font-medium text-lavender-700 transition hover:bg-white/70 hover:text-lavender-900">Panduan</a>
                <a href="#faq" class="rounded-full px-4 py-2 text-sm font-medium text-lavender-700 transition hover:bg-white/70 hover:text-lavender-900">FAQ</a>
            </div>

            <div class="hidden items-center gap-2 lg:flex">
                @auth
                    @if (auth()->user()->hasOperationalAccess())
                        <span class="hidden text-sm font-medium text-lavender-600 xl:inline">
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
                    <a href="{{ route('login.form') }}" class="rounded-full px-4 py-2 text-sm font-semibold text-lavender-800 transition hover:bg-white/70">Masuk</a>
                    <x-landing.button href="{{ route('register') }}" size="sm">
                        <x-heroicon-o-arrow-right-end-on-rectangle class="h-4 w-4" />
                        Daftar
                    </x-landing.button>
                @endauth
            </div>

            <button type="button" data-menu-toggle aria-expanded="false" aria-label="Buka menu"
                    class="inline-flex items-center justify-center rounded-lg border border-lavender-200 bg-white/70 p-2 text-lavender-700 backdrop-blur transition hover:bg-white group-data-[scrolled]:border-lavender-100 group-data-[scrolled]:bg-white/80 lg:hidden">
                <x-heroicon-o-bars-3 class="h-5 w-5" />
            </button>
        </nav>

        {{-- Mobile menu --}}
        <div data-mobile-menu class="hidden border-t border-lavender-100 bg-white px-4 pb-5 pt-3 shadow-lg lg:hidden">
            <div class="flex flex-col gap-1">
                <a href="#layanan" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-lavender-50">Layanan</a>
                <a href="#persyaratan" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-lavender-50">Persyaratan</a>
                <a href="#panduan" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-lavender-50">Panduan</a>
                <a href="#faq" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-lavender-50">FAQ</a>
                @auth
                    <div class="mt-2 rounded-xl bg-lavender-50 p-4">
                        <p class="text-xs text-slate-500">Masuk sebagai</p>
                        <p class="truncate text-sm font-semibold text-lavender-800">{{ auth()->user()->name }}</p>
                        <x-landing.button href="{{ auth()->user()->homeUrl() }}" class="mt-3 w-full">
                            <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                            {{ auth()->user()->hasOperationalAccess() ? 'Buka Panel' : 'Lanjutkan Pendaftaran' }}
                        </x-landing.button>
                    </div>
                @else
                    <a href="{{ route('login.form') }}" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-lavender-50">Masuk</a>
                    <x-landing.button href="{{ route('register') }}" class="mt-2 w-full">
                        <x-heroicon-o-user-plus class="h-4 w-4" />
                        Buat Akun
                    </x-landing.button>
                @endauth
            </div>
        </div>
    </header>
