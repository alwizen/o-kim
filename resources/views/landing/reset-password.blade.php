<x-landing.auth-layout
    title="Atur ulang kata sandi"
    subtitle="Buat kata sandi baru untuk akun O-KIM Anda."
>
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="block text-sm font-semibold text-ocean-800">Email</label>
            <input type="email" id="email" name="email" required autocomplete="email"
                   value="{{ old('email', $email) }}"
                   placeholder="nama@perusahaan.com"
                   class="mt-2 block w-full rounded-xl border border-ocean-200 bg-white px-4 py-3 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-ocean-500 focus:outline-none focus:ring-2 focus:ring-ocean-200">
            @error('email')
                <p class="mt-2 flex items-center gap-1.5 text-sm text-red-600"><x-heroicon-o-exclamation-circle class="h-4 w-4" />{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-semibold text-ocean-800">Kata Sandi Baru</label>
            <div class="relative mt-2">
                <input type="password" id="password" name="password" required autocomplete="new-password"
                       placeholder="••••••••"
                       class="block w-full rounded-xl border border-ocean-200 bg-white px-4 py-3 pr-12 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-ocean-500 focus:outline-none focus:ring-2 focus:ring-ocean-200">
                <button type="button" data-password-toggle="password" aria-label="Tampilkan kata sandi"
                        class="absolute top-1/2 right-2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 transition hover:bg-ocean-50 hover:text-ocean-700">
                    <x-heroicon-o-eye class="h-5 w-5" data-icon-show />
                    <x-heroicon-o-eye-slash class="hidden h-5 w-5" data-icon-hide />
                </button>
            </div>
            @error('password')
                <p class="mt-2 flex items-center gap-1.5 text-sm text-red-600"><x-heroicon-o-exclamation-circle class="h-4 w-4" />{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-semibold text-ocean-800">Ulangi Kata Sandi Baru</label>
            <div class="relative mt-2">
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                       placeholder="••••••••"
                       class="block w-full rounded-xl border border-ocean-200 bg-white px-4 py-3 pr-12 text-sm shadow-sm transition placeholder:text-slate-400 focus:border-ocean-500 focus:outline-none focus:ring-2 focus:ring-ocean-200">
                <button type="button" data-password-toggle="password_confirmation" aria-label="Tampilkan kata sandi"
                        class="absolute top-1/2 right-2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 transition hover:bg-ocean-50 hover:text-ocean-700">
                    <x-heroicon-o-eye class="h-5 w-5" data-icon-show />
                    <x-heroicon-o-eye-slash class="hidden h-5 w-5" data-icon-hide />
                </button>
            </div>
            @error('password_confirmation')
                <p class="mt-2 flex items-center gap-1.5 text-sm text-red-600"><x-heroicon-o-exclamation-circle class="h-4 w-4" />{{ $message }}</p>
            @enderror
        </div>

        <p class="flex items-start gap-2 text-xs leading-relaxed text-slate-500">
            <x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-ocean-400" />
            Minimal 8 karakter, mengandung huruf besar, huruf kecil, dan angka.
        </p>

        <button type="submit"
                class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-ocean-700 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-ocean-900/15 transition hover:-translate-y-0.5 hover:bg-ocean-800 hover:shadow-xl">
            <x-heroicon-o-key class="h-4 w-4" />
            Simpan Kata Sandi Baru
        </button>
    </form>

    <script>
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordToggle);
                const visible = input.type === 'text';
                input.type = visible ? 'password' : 'text';
                button.querySelector('[data-icon-show]').classList.toggle('hidden', !visible);
                button.querySelector('[data-icon-hide]').classList.toggle('hidden', visible);
                button.setAttribute('aria-label', visible ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
            });
        });
    </script>

    <x-slot:footer>
        <p class="mt-8 text-center text-sm text-slate-600">
            Tautan sudah kedaluwarsa?
            <a href="{{ route('password.request') }}" class="font-semibold text-ocean-700 underline-offset-4 hover:underline">Minta tautan baru</a>
        </p>
        <p class="mt-4 text-center text-xs text-slate-400">
            <a href="{{ route('landing') }}" class="transition hover:text-ocean-600">&larr; Kembali ke beranda</a>
        </p>
    </x-slot:footer>
</x-landing.auth-layout>
