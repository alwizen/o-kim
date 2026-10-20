<x-landing.auth-layout
    title="Masuk ke akun Anda"
    subtitle="Gunakan akun Google atau email dan kata sandi yang terdaftar."
>
    <form method="POST" action="{{ route('login.attempt') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-semibold text-ocean-800">Email</label>
            <input type="email" id="email" name="email" required autocomplete="email" autofocus
                   value="{{ old('email') }}"
                   placeholder="nama@perusahaan.com"
                   class="mt-2 block w-full rounded-xl border border-ocean-200 bg-white px-4 py-3 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-ocean-500 focus:outline-none focus:ring-2 focus:ring-ocean-200">
            @error('email')
                <p class="mt-2 flex items-center gap-1.5 text-sm text-red-600"><x-heroicon-o-exclamation-circle class="h-4 w-4" />{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-semibold text-ocean-800">Kata Sandi</label>
            <input type="password" id="password" name="password" required autocomplete="current-password"
                   placeholder="••••••••"
                   class="mt-2 block w-full rounded-xl border border-ocean-200 bg-white px-4 py-3 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-ocean-500 focus:outline-none focus:ring-2 focus:ring-ocean-200">
            @error('password')
                <p class="mt-2 flex items-center gap-1.5 text-sm text-red-600"><x-heroicon-o-exclamation-circle class="h-4 w-4" />{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2.5 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1"
                   class="h-4 w-4 rounded border-ocean-300 text-ocean-700 focus:ring-ocean-300">
            Ingat saya
        </label>

        <button type="submit"
                class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-ocean-700 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-ocean-900/15 transition hover:-translate-y-0.5 hover:bg-ocean-800 hover:shadow-xl">
            <x-heroicon-o-arrow-right-end-on-rectangle class="h-4 w-4" />
            Masuk
        </button>
    </form>

    <div class="my-7 flex items-center gap-4">
        <span class="h-px flex-1 bg-ocean-100"></span>
        <span class="text-xs font-medium uppercase tracking-widest text-slate-400">atau</span>
        <span class="h-px flex-1 bg-ocean-100"></span>
    </div>

    <a href="{{ route('auth.google') }}"
       class="inline-flex w-full items-center justify-center gap-3 rounded-full border border-ocean-200 bg-white px-6 py-3 text-sm font-semibold text-ocean-800 transition hover:-translate-y-0.5 hover:border-ocean-400 hover:bg-ocean-50">
        <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1Z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.65l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23Z"/>
            <path fill="#FBBC05" d="M5.84 14.11a6.6 6.6 0 0 1 0-4.22V7.05H2.18a11 11 0 0 0 0 9.9l3.66-2.84Z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.05l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38Z"/>
        </svg>
        Masuk dengan Google
    </a>

    <x-slot:footer>
        <p class="mt-8 text-center text-sm text-slate-600">
            Belum punya akun?
            <a href="{{ route('register') }}" class="font-semibold text-ocean-700 underline-offset-4 hover:underline">Daftar di sini</a>
        </p>
        <p class="mt-2 text-center text-xs text-slate-500">
            Petugas internal (HSE/Distribusi/QQ/FTM)?
            <a href="{{ route('register.internal') }}" class="font-semibold text-ocean-700 underline-offset-4 hover:underline">Daftar internal</a>
        </p>
        <p class="mt-4 text-center text-xs text-slate-400">
            <a href="{{ route('landing') }}" class="transition hover:text-ocean-600">&larr; Kembali ke beranda</a>
        </p>
    </x-slot:footer>
</x-landing.auth-layout>
