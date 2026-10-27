    {{-- Header --}}
    <header data-header
            class="group fixed inset-x-0 top-0 z-40 border-b border-transparent bg-transparent transition-all duration-300 data-[scrolled]:border-ocean-100 data-[scrolled]:bg-white/85 data-[scrolled]:shadow-sm data-[scrolled]:backdrop-blur">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8" aria-label="Navigasi utama">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white p-1 shadow-sm ring-1 ring-black/5">
                    <img src="{{ asset('img/v2.png') }}" alt="Logo O-KIM" class="h-full w-full object-contain" width="40" height="40">
                </span>
                <span class="flex flex-col leading-none">
                    <span class="text-base font-bold tracking-tight text-white transition-colors group-data-[scrolled]:text-ocean-900">O-KIM</span>
                    <span class="text-[11px] font-medium text-white/70 transition-colors group-data-[scrolled]:text-ocean-600">Fuel Terminal Tegal</span>
                </span>
            </a>

            <div class="hidden items-center gap-1 lg:flex">
                <a href="#layanan" class="rounded-full px-4 py-2 text-sm font-medium text-white/90 transition hover:bg-white/10 hover:text-white group-data-[scrolled]:text-ocean-700 group-data-[scrolled]:hover:bg-white/70 group-data-[scrolled]:hover:text-ocean-900">Layanan</a>
                <a href="#persyaratan" class="rounded-full px-4 py-2 text-sm font-medium text-white/90 transition hover:bg-white/10 hover:text-white group-data-[scrolled]:text-ocean-700 group-data-[scrolled]:hover:bg-white/70 group-data-[scrolled]:hover:text-ocean-900">Persyaratan</a>
                <a href="#panduan" class="rounded-full px-4 py-2 text-sm font-medium text-white/90 transition hover:bg-white/10 hover:text-white group-data-[scrolled]:text-ocean-700 group-data-[scrolled]:hover:bg-white/70 group-data-[scrolled]:hover:text-ocean-900">Panduan</a>
                <a href="#faq" class="rounded-full px-4 py-2 text-sm font-medium text-white/90 transition hover:bg-white/10 hover:text-white group-data-[scrolled]:text-ocean-700 group-data-[scrolled]:hover:bg-white/70 group-data-[scrolled]:hover:text-ocean-900">FAQ</a>
            </div>

            <div class="hidden items-center gap-2 lg:flex">
                @auth
                    @if (auth()->user()->hasOperationalAccess())
                        <span class="hidden text-sm font-medium text-white/80 transition-colors group-data-[scrolled]:text-ocean-600 xl:inline">
                            {{ auth()->user()->name }}
                        </span>
                        <x-landing.button href="{{ auth()->user()->homeUrl() }}" variant="light" size="sm">
                            <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                            Buka Panel
                        </x-landing.button>
                    @else
                        <x-landing.button href="{{ auth()->user()->homeUrl() }}" variant="light" size="sm">
                            <x-heroicon-o-clipboard-document-check class="h-4 w-4" />
                            Lanjutkan Pendaftaran
                        </x-landing.button>
                    @endif
                @else
                    <div class="relative" data-dropdown>
                        <button type="button" data-dropdown-trigger
                                aria-expanded="false" aria-haspopup="true"
                                class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10 group-data-[scrolled]:text-ocean-800 group-data-[scrolled]:hover:bg-white/70">
                            Masuk
                            <x-heroicon-m-chevron-down class="h-4 w-4 transition-transform" data-dropdown-icon />
                        </button>
                        <div data-dropdown-panel hidden
                             class="absolute right-0 top-full z-50 mt-2 w-56 overflow-hidden rounded-xl border border-ocean-100 bg-white shadow-xl shadow-ocean-900/10">
                            <p class="border-b border-ocean-50 bg-ocean-50/60 px-4 py-2.5 text-xs font-medium text-slate-500">Masuk sebagai</p>
                            <a href="{{ route('login.form') }}"
                               class="flex items-start gap-3 px-4 py-3 transition hover:bg-ocean-50">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-ocean-100 text-ocean-700">
                                    <x-heroicon-o-truck class="h-4 w-4" />
                                </span>
                                <span>
                                    <span class="block text-sm font-semibold text-slate-800">Transportir</span>
                                    <span class="block text-xs text-slate-500">Pengajuan & kelola KIM armada</span>
                                </span>
                            </a>
                            <a href="/admin/login"
                               class="flex items-start gap-3 px-4 py-3 transition hover:bg-ocean-50">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-ocean-100 text-ocean-700">
                                    <x-heroicon-o-identification class="h-4 w-4" />
                                </span>
                                <span>
                                    <span class="block text-sm font-semibold text-slate-800">Petugas Internal</span>
                                    <span class="block text-xs text-slate-500">Pemeriksaan, approval & admin</span>
                                </span>
                            </a>
                        </div>
                    </div>

                    <div class="relative" data-dropdown>
                        <button type="button" data-dropdown-trigger
                                aria-expanded="false" aria-haspopup="true"
                                class="inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-2 text-sm font-semibold text-ocean-800 shadow-lg shadow-ocean-900/10 transition-all duration-300 hover:bg-ocean-50 hover:-translate-y-0.5">
                            Daftar
                            <x-heroicon-m-chevron-down class="h-4 w-4 transition-transform" data-dropdown-icon />
                        </button>
                        <div data-dropdown-panel hidden
                             class="absolute right-0 top-full z-50 mt-2 w-56 overflow-hidden rounded-xl border border-ocean-100 bg-white shadow-xl shadow-ocean-900/10">
                            <p class="border-b border-ocean-50 bg-ocean-50/60 px-4 py-2.5 text-xs font-medium text-slate-500">Daftar sebagai</p>
                            <a href="{{ route('register') }}"
                               class="flex items-start gap-3 px-4 py-3 transition hover:bg-ocean-50">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-ocean-100 text-ocean-700">
                                    <x-heroicon-o-truck class="h-4 w-4" />
                                </span>
                                <span>
                                    <span class="block text-sm font-semibold text-slate-800">Transportir</span>
                                    <span class="block text-xs text-slate-500">Perusahaan angkutan / mobil tangki</span>
                                </span>
                            </a>
                            <a href="{{ route('register.internal') }}"
                               class="flex items-start gap-3 px-4 py-3 transition hover:bg-ocean-50">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-ocean-100 text-ocean-700">
                                    <x-heroicon-o-identification class="h-4 w-4" />
                                </span>
                                <span>
                                    <span class="block text-sm font-semibold text-slate-800">Petugas Internal</span>
                                    <span class="block text-xs text-slate-500">HSSE, QQ, Distribusi, FTM, dll.</span>
                                </span>
                            </a>
                        </div>
                    </div>
                @endauth
            </div>

            <button type="button" data-menu-toggle aria-expanded="false" aria-label="Buka menu"
                    class="inline-flex items-center justify-center rounded-lg border border-white/40 bg-white/10 p-2 text-white backdrop-blur transition hover:bg-white/20 group-data-[scrolled]:border-ocean-100 group-data-[scrolled]:bg-white/80 group-data-[scrolled]:text-ocean-700 lg:hidden">
                <x-heroicon-o-bars-3 class="h-5 w-5" />
            </button>
        </nav>

        {{-- Mobile menu --}}
        <div data-mobile-menu class="hidden border-t border-ocean-100 bg-white px-4 pb-5 pt-3 shadow-lg lg:hidden">
            <div class="flex flex-col gap-1">
                <a href="#layanan" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-ocean-50">Layanan</a>
                <a href="#persyaratan" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-ocean-50">Persyaratan</a>
                <a href="#panduan" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-ocean-50">Panduan</a>
                <a href="#faq" class="rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-ocean-50">FAQ</a>
                @auth
                    <div class="mt-2 rounded-xl bg-ocean-50 p-4">
                        <p class="text-xs text-slate-500">Masuk sebagai</p>
                        <p class="truncate text-sm font-semibold text-ocean-800">{{ auth()->user()->name }}</p>
                        <x-landing.button href="{{ auth()->user()->homeUrl() }}" class="mt-3 w-full">
                            <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                            {{ auth()->user()->hasOperationalAccess() ? 'Buka Panel' : 'Lanjutkan Pendaftaran' }}
                        </x-landing.button>
                    </div>
                @else
                    <div class="mt-2" data-dropdown>
                        <button type="button" data-dropdown-trigger
                                aria-expanded="false" aria-haspopup="true"
                                class="flex w-full items-center justify-between rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-ocean-50">
                            Masuk
                            <x-heroicon-m-chevron-down class="h-4 w-4" />
                        </button>
                        <div data-dropdown-panel hidden class="flex flex-col gap-1 pb-2">
                            <a href="{{ route('login.form') }}" class="rounded-lg px-4 py-2.5 text-sm text-slate-600 hover:bg-ocean-50">
                                🚛 Masuk sebagai Transportir
                            </a>
                            <a href="/admin/login" class="rounded-lg px-4 py-2.5 text-sm text-slate-600 hover:bg-ocean-50">
                                🪪 Masuk sebagai Petugas Internal
                            </a>
                        </div>
                    </div>

                    <div data-dropdown>
                        <button type="button" data-dropdown-trigger
                                aria-expanded="false" aria-haspopup="true"
                                class="flex w-full items-center justify-between rounded-lg px-4 py-3 text-sm font-medium text-slate-700 hover:bg-ocean-50">
                            Daftar
                            <x-heroicon-m-chevron-down class="h-4 w-4" />
                        </button>
                        <div data-dropdown-panel hidden class="flex flex-col gap-1 pb-2">
                            <a href="{{ route('register') }}" class="rounded-lg px-4 py-2.5 text-sm text-slate-600 hover:bg-ocean-50">
                                🚛 Daftar sebagai Transportir
                            </a>
                            <a href="{{ route('register.internal') }}" class="rounded-lg px-4 py-2.5 text-sm text-slate-600 hover:bg-ocean-50">
                                🪪 Daftar sebagai Petugas Internal
                            </a>
                        </div>
                    </div>
                @endauth
            </div>
        </div>
    </header>
