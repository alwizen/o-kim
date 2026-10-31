<x-landing.auth-layout
    title="Lupa kata sandi"
    subtitle="Masukkan email akun Anda. Kami akan mengirim tautan untuk mengatur ulang kata sandi."
>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
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

        <button type="submit"
                class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-ocean-700 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-ocean-900/15 transition hover:-translate-y-0.5 hover:bg-ocean-800 hover:shadow-xl">
            <x-heroicon-o-paper-airplane class="h-4 w-4" />
            Kirim Tautan Reset
        </button>
    </form>

    <p class="mt-5 flex items-start gap-2 text-xs leading-relaxed text-slate-500">
        <x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-ocean-400" />
        Fitur ini hanya berlaku untuk akun yang terdaftar dengan email dan kata sandi. Akun yang
        terdaftar melalui Google tidak memiliki kata sandi sehingga tidak dapat direset.
    </p>

    <x-slot:footer>
        <p class="mt-8 text-center text-sm text-slate-600">
            Ingat kata sandi Anda?
            <a href="{{ route('login.form') }}" class="font-semibold text-ocean-700 underline-offset-4 hover:underline">Masuk di sini</a>
        </p>
        <p class="mt-2 text-center text-xs text-slate-500">
            Petugas internal?
            <a href="/admin/login" class="font-semibold text-ocean-700 underline-offset-4 hover:underline">Masuk melalui panel admin</a>
        </p>
        <p class="mt-4 text-center text-xs text-slate-400">
            <a href="{{ route('landing') }}" class="transition hover:text-ocean-600">&larr; Kembali ke Halaman utama</a>
        </p>
    </x-slot:footer>
</x-landing.auth-layout>
