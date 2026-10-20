<x-landing.auth-layout
    title="Menunggu Verifikasi"
    subtitle="Akun internal Anda telah dibuat dan sedang menunggu persetujuan super admin."
>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
        <div class="flex items-start gap-3">
            <x-heroicon-o-clock class="mt-0.5 h-6 w-6 shrink-0 text-amber-600" />
            <div class="text-sm leading-relaxed text-amber-800">
                <p class="font-semibold">Akun Anda belum aktif</p>
                <p class="mt-1">
                    Admin akan memverifikasi pendaftaran Anda. Anda akan menerima notifikasi
                    melalui email setelah akun disetujui atau ditolak.
                </p>
            </div>
        </div>
    </div>

    <x-slot:footer>
        <div class="mt-8 flex items-center justify-between text-sm">
            <a href="{{ route('landing') }}" class="text-slate-400 transition hover:text-ocean-600">&larr; Beranda</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="font-medium text-slate-600 transition hover:text-ocean-700">Keluar</button>
            </form>
        </div>
    </x-slot:footer>
</x-landing.auth-layout>
