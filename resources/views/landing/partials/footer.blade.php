    {{-- Footer --}}
    <footer class="border-t border-ocean-100 bg-ocean-50/60">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2 lg:col-span-2">
                    <div class="flex items-center gap-3">
                        <img src="{{ asset('img/logo.svg') }}" alt="Logo O-KIM" class="h-10 w-auto">
                        <div class="flex flex-col leading-none">
                            <span class="text-base font-bold text-ocean-800">O-KIM</span>
                            <span class="text-xs text-slate-500">Online Kartu Izin Masuk</span>
                        </div>
                    </div>
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-slate-600">
                        Sistem internal operasional untuk pengelolaan Kartu Izin Masuk kendaraan tangki
                        di Fuel Terminal Tegal.
                    </p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-ocean-900">Navigasi</h3>
                    <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                        <li><a href="#layanan" class="transition hover:text-ocean-700">Layanan</a></li>
                        <li><a href="#persyaratan" class="transition hover:text-ocean-700">Persyaratan</a></li>
                        <li><a href="#panduan" class="transition hover:text-ocean-700">Panduan</a></li>
                        <li><a href="#faq" class="transition hover:text-ocean-700">FAQ</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-ocean-900">Akses</h3>
                    <ul class="mt-4 space-y-2.5 text-sm text-slate-600">
                        @auth
                            <li><a href="{{ auth()->user()->homeUrl() }}" class="font-medium transition hover:text-ocean-700">{{ auth()->user()->hasOperationalAccess() ? 'Buka Panel Saya' : 'Lanjutkan Pendaftaran' }}</a></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="transition hover:text-ocean-700">Keluar</button>
                                </form>
                            </li>
                        @else
                            <li><a href="{{ route('register') }}" class="transition hover:text-ocean-700">Pendaftaran Transportir</a></li>
                            <li><a href="{{ route('login.form') }}" class="transition hover:text-ocean-700">Masuk ke Akun</a></li>
                        @endauth
                        <li><a href="/admin/login" class="transition hover:text-ocean-700">Masuk Panel Admin</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-ocean-100 pt-6 text-sm text-slate-500 sm:flex-row">
                <p>&copy; {{ date('Y') }} O-KIM Fuel Terminal Tegal. Seluruh hak dilindungi.</p>
                {{-- <p class="flex items-center gap-2">
                    <x-heroicon-o-lock-closed class="h-4 w-4" />
                    Sistem internal — akses terbatas
                </p> --}}
            </div>
        </div>
    </footer>
